<?php

namespace Tests\Feature;

use App\Models\Auth\MobileSession;
use App\Models\Auth\User;
use App\Models\Media\Artist;
use App\Models\Media\Album;
use App\Models\Media\Playlist;
use App\Models\Media\Track;
use App\Services\Auth\InviteService;
use App\Services\Auth\MobileSessionLimitException;
use App\Services\Auth\MobileTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InviteAndMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private ?string $keyPath = null;

    protected function tearDown(): void
    {
        if ($this->keyPath && is_file($this->keyPath)) {
            unlink($this->keyPath);
        }
        parent::tearDown();
    }

    public function test_invite_is_hashed_limited_and_redeemed_without_exposing_code(): void
    {
        config(['instance.registration_mode' => 'invite_only']);
        Role::findOrCreate('user', 'web');
        $admin = User::factory()->create();
        $service = app(InviteService::class);
        $created = $service->create($admin, 'friend@example.test', 1, 7);

        $this->assertNotSame($created['code'], $created['invite']->code_hash);
        $this->assertSame(hash('sha256', $created['code']), $created['invite']->code_hash);

        $user = $service->redeem($created['code'], [
            'name' => 'Friend', 'email' => 'friend@example.test', 'password' => 'SafePassword123!',
        ]);
        $this->assertSame('friend@example.test', $user->email);
        $this->assertDatabaseHas('invite_redemptions', ['invite_id' => $created['invite']->id, 'user_id' => $user->id]);
        $this->assertSame(1, $created['invite']->fresh()->uses_count);

        $this->postJson('/api/v1/mobile/invites/redeem', [
            'invite_code' => $created['code'], 'name' => 'Another', 'email' => 'other@example.test',
            'password' => 'SafePassword123!', 'password_confirmation' => 'SafePassword123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('invite_code');
    }

    public function test_mobile_api_requires_a_valid_access_token_and_hides_unpublished_tracks(): void
    {
        $this->configureSigningKey();
        $user = User::factory()->create();
        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox']);
        $public = Track::create([
            'artist_id' => $artist->id, 'title' => 'Public', 'slug' => 'public',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => true,
        ]);
        $private = Track::create([
            'artist_id' => $artist->id, 'title' => 'Private', 'slug' => 'private',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => false,
        ]);
        $access = app(MobileTokenService::class)->issue($user)['access_token'];

        $this->getJson('/api/v1/mobile/catalogue')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/catalogue', ['Authorization' => "Bearer {$access}"])
            ->assertOk()->assertJsonPath('data.0.id', $public->public_id)
            ->assertDontSee($private->title);
        $this->getJson("/api/v1/mobile/tracks/{$private->public_id}", ['Authorization' => "Bearer {$access}"])
            ->assertNotFound();
        $this->getJson('/api/v1/mobile/catalogue', ['Authorization' => 'Bearer invalid'])
            ->assertUnauthorized();
    }

    public function test_mobile_search_exposes_authenticated_artist_avatar(): void
    {
        $this->configureSigningKey();
        Storage::fake('s3');
        $artist = Artist::create([
            'name' => 'Nox', 'slug' => 'nox', 'avatar' => 'artists/nox/avatar.png',
        ]);
        Track::create([
            'artist_id' => $artist->id, 'title' => 'Public', 'slug' => 'public',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => true,
        ]);
        Storage::disk('s3')->put($artist->avatar, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='
        ));
        $url = "/api/v1/mobile/artists/{$artist->public_id}/artwork";
        $headers = ['Authorization' => 'Bearer '.app(MobileTokenService::class)
            ->issue(User::factory()->create())['access_token']];

        $this->getJson('/api/v1/mobile/search?q=Nox', $headers)
            ->assertOk()->assertJsonPath('data.artists.0.avatar_url', url($url));
        $this->get($url)->assertUnauthorized();
        $this->get($url, $headers)->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_mobile_library_shows_each_recent_track_once(): void
    {
        $this->configureSigningKey();
        $user = User::factory()->create();
        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox']);
        $repeated = Track::create([
            'artist_id' => $artist->id, 'title' => 'Repeated', 'slug' => 'repeated',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => true,
        ]);
        $older = Track::create([
            'artist_id' => $artist->id, 'title' => 'Older', 'slug' => 'older',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => true,
        ]);
        $user->playLogs()->forceCreate([
            'track_id' => $older->id, 'played_duration_seconds' => 30,
            'completed' => false, 'created_at' => now()->subMinutes(20),
        ]);
        for ($i = 10; $i > 0; $i--) {
            $user->playLogs()->forceCreate([
                'track_id' => $repeated->id, 'played_duration_seconds' => 60,
                'completed' => false, 'created_at' => now()->subMinutes($i),
            ]);
        }

        $token = app(MobileTokenService::class)->issue($user)['access_token'];
        $this->getJson('/api/v1/mobile/library', ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonCount(2, 'data.history')
            ->assertJsonPath('data.history.0.track.id', $repeated->public_id)
            ->assertJsonPath('data.history.1.track.id', $older->public_id);
        $this->assertSame(11, $user->playLogs()->count());
    }

    public function test_mobile_artwork_serves_album_and_artist_images_with_access_token(): void
    {
        $this->configureSigningKey();
        Storage::fake('s3');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
        Storage::disk('s3')->put('artists/nox.png', $image);
        Storage::disk('s3')->put('albums/first.png', $image);
        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox', 'avatar' => 'artists/nox.png']);
        $track = Track::create([
            'artist_id' => $artist->id, 'title' => 'First', 'slug' => 'first',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => true,
        ]);
        $album = Album::create([
            'artist_id' => $artist->id, 'title' => 'First', 'slug' => 'first',
            'cover_image' => 'albums/first.png',
        ]);
        $album->tracks()->attach($track->id, ['sort_order' => 1]);
        $token = app(MobileTokenService::class)->issue(User::factory()->create())['access_token'];
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson("/api/v1/mobile/artists/{$artist->public_id}/artwork")->assertUnauthorized();
        $this->getJson("/api/v1/mobile/artists/{$artist->public_id}/artwork", $headers)->assertOk();
        $this->getJson("/api/v1/mobile/albums/{$album->public_id}/artwork", $headers)->assertOk();
        $this->getJson("/api/v1/mobile/tracks/{$track->public_id}/artwork", $headers)->assertOk();
        $this->getJson('/api/v1/mobile/search?q=Nox', $headers)
            ->assertJsonPath('data.artists.0.artwork_url', url("/api/v1/mobile/artists/{$artist->public_id}/artwork"));
    }

    public function test_mobile_playlist_mutations_are_limited_to_the_owner(): void
    {
        $this->configureSigningKey();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $playlist = Playlist::create(['user_id' => $owner->id, 'title' => 'Private list', 'is_public' => false]);
        $token = app(MobileTokenService::class)->issue($other)['access_token'];

        $this->deleteJson("/api/v1/mobile/playlists/{$playlist->public_id}", [], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
        $this->assertDatabaseHas('playlists', ['id' => $playlist->id]);
    }

    public function test_settings_password_change_revokes_mobile_sessions_and_owner_can_revoke_devices(): void
    {
        $this->configureSigningKey();
        $user = User::factory()->create(['password' => Hash::make('OriginalPassword123!')]);
        app(MobileTokenService::class)->issue($user);
        $session = MobileSession::query()->sole();

        $this->actingAs($user)->put(route('settings.password'), [
            'current_password' => 'OriginalPassword123!',
            'password' => 'NextPassword123!', 'password_confirmation' => 'NextPassword123!',
        ])->assertRedirect();
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertTrue(Hash::check('NextPassword123!', $user->fresh()->password));

        // Someone else, in their own browser session.
        $other = User::factory()->create();
        $this->flushSession();
        $this->actingAs($other)->delete(route('settings.devices.revoke', $session))->assertNotFound();
    }

    public function test_user_can_export_only_their_own_playlists_and_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Playlist::create(['user_id' => $user->id, 'title' => 'Mine', 'is_public' => false]);
        Playlist::create(['user_id' => $other->id, 'title' => 'Theirs', 'is_public' => false]);

        $response = $this->actingAs($user)->get(route('settings.export'));
        $response->assertOk()->assertDownload();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Mine', $export['playlists'][0]['title']);
        $this->assertCount(1, $export['playlists']);
        $this->assertSame($user->email, $export['account']['email']);
    }

    public function test_mobile_preferences_are_private_and_validate_quality(): void
    {
        $this->configureSigningKey();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = app(MobileTokenService::class)->issue($user)['access_token'];
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/mobile/preferences')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/preferences', $headers)
            ->assertOk()->assertJsonPath('data.audio_quality', 'auto')
            ->assertJsonPath('data.autoplay', true);
        $this->patchJson('/api/v1/mobile/preferences', [
            'audio_quality' => 'high', 'autoplay' => false,
            'record_listening_history' => false,
        ], $headers)->assertOk()->assertJsonPath('data.audio_quality', 'high');
        $this->patchJson('/api/v1/mobile/preferences', [
            'audio_quality' => 'invalid',
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('audio_quality');

        $this->assertSame('high', $user->fresh()->preference->audio_quality);
        $this->assertFalse($user->fresh()->preference->record_listening_history);
        $this->assertNull($other->fresh()->preference);

        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox']);
        $track = Track::create([
            'artist_id' => $artist->id, 'title' => 'No history', 'slug' => 'no-history',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => true,
        ]);
        $this->postJson('/api/v1/mobile/play-logs', [
            'track_id' => $track->public_id, 'played_duration_seconds' => 20,
            'completed' => true, 'playback_id' => 'mobile-privacy-test',
        ], $headers)->assertNoContent();
        $this->assertDatabaseCount('user_play_logs', 0);
    }

    public function test_mobile_session_limit_does_not_create_an_extra_session(): void
    {
        $this->configureSigningKey();
        config(['stream.refresh.max_sessions_per_user' => 1]);
        $user = User::factory()->create();
        $service = app(MobileTokenService::class);
        $service->issue($user);

        $this->expectException(MobileSessionLimitException::class);
        try {
            $service->issue($user);
        } finally {
            $this->assertDatabaseCount('mobile_sessions', 1);
        }
    }

    private function configureSigningKey(): void
    {
        $this->keyPath = tempnam(sys_get_temp_dir(), 'indie-api-key-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        file_put_contents($this->keyPath, $privateKey);
        config([
            'stream.jwt.private_key_path' => $this->keyPath,
            'stream.jwt.key_id' => 'stream-v1',
            'stream.jwt.issuer' => 'https://identity.indie-house.test',
            'stream.jwt.audience' => 'indie-house-mobile',
            'stream.auth_rate_limit.store' => 'array',
        ]);
    }
}
