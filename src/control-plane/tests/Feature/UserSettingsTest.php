<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_requires_password_for_email_and_stores_avatar(): void
    {
        Storage::fake('s3');
        $this->freezeTime();
        $user = User::factory()->create(['password' => Hash::make('CurrentPassword123!')]);

        $this->actingAs($user)->put(route('settings.profile'), [
            'name' => 'New name', 'email' => 'new@example.test',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);

        $this->actingAs($user)->put(route('settings.profile'), [
            'name' => 'New name', 'email' => 'new@example.test',
            'current_password' => 'CurrentPassword123!',
            'avatar' => UploadedFile::fake()->image('portrait.png'),
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('New name', $user->name);
        $this->assertSame('new@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        Storage::disk('s3')->assertExists($user->avatar_path);
        $this->assertStringStartsWith("avatars/{$user->id}/", $user->avatar_path);

        $first = $user->avatar_path;
        $this->actingAs($user)->put(route('settings.profile'), [
            'name' => 'New name', 'email' => 'new@example.test',
            'avatar' => UploadedFile::fake()->image('second.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        Storage::disk('s3')->assertMissing($first);
        Storage::disk('s3')->assertExists($user->fresh()->avatar_path);
        $this->actingAs($user)->get(route('settings.show'))->assertOk()
            ->assertSee(Storage::disk('s3')->temporaryUrl($user->fresh()->avatar_path, now()->addMinutes(30)), false);
    }

    public function test_playback_preferences_are_saved_per_account_and_invalid_quality_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->put(route('settings.playback'), [
            'audio_quality' => 'high', 'autoplay' => '0',
        ])->assertRedirect();
        $this->assertSame('high', $user->fresh()->preference->audio_quality);
        $this->assertFalse($user->fresh()->preference->autoplay);
        $this->assertNull($other->fresh()->preference);

        $this->actingAs($user)->put(route('settings.playback'), [
            'audio_quality' => 'lossless', 'autoplay' => '1',
        ])->assertSessionHasErrors('audio_quality');
        $this->assertSame('high', $user->fresh()->preference->audio_quality);

        $this->actingAs($user)->get(route('settings.show'))
            ->assertOk()->assertSee('data-audio-quality="high"', false)
            ->assertSee('data-autoplay="0"', false);
    }

    public function test_settings_lists_the_users_active_mobile_sessions(): void
    {
        $keyPath = tempnam(sys_get_temp_dir(), 'indie-settings-key-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        file_put_contents($keyPath, $privateKey);
        config([
            'stream.jwt.private_key_path' => $keyPath,
            'stream.jwt.key_id' => 'stream-v1',
            'stream.jwt.issuer' => 'https://identity.indie-house.test',
            'stream.jwt.audience' => 'indie-house-mobile',
            'stream.auth_rate_limit.store' => 'array',
        ]);
        $user = User::factory()->create();
        $other = User::factory()->create();

        try {
            $this->postJson('/api/v1/mobile/auth/token', [
                'email' => $user->email, 'password' => 'password',
                'device_name' => 'BlackBerry Leap', 'platform' => 'blackberry_leap',
            ])->assertOk();
            $this->postJson('/api/v1/mobile/auth/token', [
                'email' => $user->email, 'password' => 'password', 'platform' => 'ios',
            ])->assertOk();
            $this->postJson('/api/v1/mobile/auth/token', [
                'email' => $other->email, 'password' => 'password', 'device_name' => 'Other phone',
            ])->assertOk();
        } finally {
            @unlink($keyPath);
        }
        $user->mobileSessions()->create([
            'token_hash' => str_repeat('a', 64), 'password_fingerprint' => str_repeat('b', 64),
            'device_name' => 'Revoked phone', 'expires_at' => now()->addDay(), 'revoked_at' => now(),
        ]);

        $this->actingAs($user)->get(route('settings.show'))
            ->assertOk()
            ->assertSee('BlackBerry Leap')
            ->assertSee('ios')
            ->assertDontSee('Other phone')
            ->assertDontSee('Revoked phone')
            ->assertDontSee(__('settings.no_devices'));
    }

    public function test_disabling_history_stops_new_logs_and_preserves_existing_data(): void
    {
        $user = User::factory()->create();
        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox']);
        $track = Track::create([
            'artist_id' => $artist->id, 'title' => 'Signal', 'slug' => 'signal',
            'duration_seconds' => 90, 'status' => 'completed', 'is_published' => true,
        ]);
        $user->playLogs()->create([
            'track_id' => $track->id, 'played_duration_seconds' => 12,
            'completed' => false,
        ]);

        $this->actingAs($user)->put(route('settings.privacy'), [
            'record_listening_history' => '0',
        ])->assertRedirect();
        $this->actingAs($user)->postJson(route('listening-history.store'), [
            'track_id' => $track->public_id,
            'played_duration_seconds' => 20,
            'completed' => true,
            'playback_id' => 'test-playback',
        ])->assertNoContent();

        $this->assertDatabaseCount('user_play_logs', 1);
        $this->assertSame(0, $track->fresh()->play_count);
        $export = $this->actingAs($user)->get(route('settings.export'));
        $data = json_decode($export->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($data['preferences']['record_listening_history']);
        $this->assertCount(1, $data['listening_history']);
    }
}
