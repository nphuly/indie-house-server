<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use App\Services\Auth\MobileTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileBrowseTest extends TestCase
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

    public function test_browse_shows_the_newest_songs_and_albums_and_the_most_played_artists(): void
    {
        $catalogue = $this->catalogue();
        $headers = $this->bearer(User::factory()->create());

        $this->getJson('/api/v1/mobile/browse')->assertUnauthorized();
        $this->getJson('/api/v1/mobile/browse', $headers)
            ->assertOk()
            ->assertJsonPath('data.counts', ['tracks' => 3, 'albums' => 2, 'artists' => 2])
            ->assertJsonCount(3, 'data.new_tracks')
            ->assertJsonPath('data.new_tracks.0.title', 'Mưa Đêm')
            ->assertJsonPath('data.new_tracks.2.title', 'Night Signal')
            ->assertJsonPath('data.albums.0.id', $catalogue['newAlbum']->public_id)
            ->assertJsonPath('data.albums.0.track_count', 1)
            ->assertJsonPath('data.albums.1.artist.name', 'Nox An')
            ->assertJsonPath('data.artists.0.name', 'Nox An')
            ->assertJsonPath('data.artists.0.track_count', 2)
            ->assertJsonPath('data.artists.0.artwork_url', null)
            ->assertJsonMissing(['title' => 'Unreleased Signal'])
            ->assertJsonMissing(['name' => 'Ghost']);
    }

    public function test_albums_and_artists_are_listed_a_page_at_a_time(): void
    {
        $catalogue = $this->catalogue();
        $headers = $this->bearer(User::factory()->create());

        $this->getJson('/api/v1/mobile/albums', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $catalogue['newAlbum']->public_id)
            ->assertJsonPath('data.0.title', 'Bến Cũ')
            ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'total' => 2]);

        $this->getJson('/api/v1/mobile/artists?page=1', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Bến Xưa')
            ->assertJsonPath('data.1.name', 'Nox An')
            ->assertJsonPath('data.1.track_count', 2)
            ->assertJsonPath('meta.total', 2);

        foreach (range(1, 24) as $number) {
            $artist = Artist::create(['name' => "Z Artist {$number}", 'slug' => "z-artist-{$number}"]);
            $this->makeTrack($artist);
        }
        $this->getJson('/api/v1/mobile/artists?page=2', $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta', ['current_page' => 2, 'last_page' => 2, 'total' => 26]);
    }

    /** @return array{newAlbum: Album} */
    private function catalogue(): array
    {
        $nox = Artist::create(['name' => 'Nox An', 'slug' => 'nox-an']);
        $ben = Artist::create(['name' => 'Bến Xưa', 'slug' => 'ben-xua']);
        $ghost = Artist::create(['name' => 'Ghost', 'slug' => 'ghost']);

        $this->travelTo(now()->subDays(3));
        $hit = $this->makeTrack($nox, ['title' => 'Night Signal', 'play_count' => 90]);
        $this->makeTrack($nox, ['title' => 'Unreleased Signal', 'is_published' => false]);
        $hidden = $this->makeTrack($ghost, ['title' => 'Ghost Signal', 'status' => 'processing']);
        $oldAlbum = Album::create(['artist_id' => $nox->id, 'title' => 'Signal Room', 'slug' => 'signal-room']);
        $oldAlbum->tracks()->attach($hit->id, ['sort_order' => 1]);
        Album::create(['artist_id' => $ghost->id, 'title' => 'Ghost Room', 'slug' => 'ghost-room'])
            ->tracks()->attach($hidden->id, ['sort_order' => 1]);

        $this->travelTo(now()->addDay());
        $this->makeTrack($nox, ['title' => 'Quiet Signal', 'play_count' => 5]);

        $this->travelBack();
        $rain = $this->makeTrack($ben, ['title' => 'Mưa Đêm', 'play_count' => 1]);
        $newAlbum = Album::create(['artist_id' => $ben->id, 'title' => 'Bến Cũ', 'slug' => 'ben-cu']);
        $newAlbum->tracks()->attach($rain->id, ['sort_order' => 1]);

        return ['newAlbum' => $newAlbum];
    }

    private function makeTrack(Artist $artist, array $overrides = []): Track
    {
        return Track::create([
            'artist_id' => $artist->id,
            'title' => 'Track '.str()->random(8),
            'slug' => str()->random(10),
            'duration_seconds' => 192,
            'status' => 'completed',
            'is_published' => true,
            ...$overrides,
        ]);
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        if (! $this->keyPath) {
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

        return ['Authorization' => 'Bearer '.app(MobileTokenService::class)->issue($user)['access_token']];
    }
}
