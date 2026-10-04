<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Playlist;
use App\Models\Media\Track;
use Database\Seeders\ListenerDemoSeeder;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ListeningAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_only_exposes_completed_published_tracks(): void
    {
        $artist = Artist::create(['name' => 'Nox An', 'slug' => 'nox-an']);
        $publicTrack = $this->makeTrack($artist, ['title' => 'Night Signal']);
        $privateTrack = $this->makeTrack($artist, ['title' => 'Unreleased Signal', 'is_published' => false]);

        $response = $this->get(route('listen.home'));

        $response->assertOk()
            ->assertSee($publicTrack->title)
            ->assertDontSee($privateTrack->title);
    }

    public function test_guests_are_redirected_from_private_library(): void
    {
        $this->get(route('library.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_listeners_can_render_the_catalogue_without_playlists(): void
    {
        $artist = Artist::create(['name' => 'Mây Đen', 'slug' => 'may-den']);
        $this->makeTrack($artist);

        $this->actingAs(User::factory()->create())
            ->get(route('listen.home'))
            ->assertOk();
    }

    public function test_only_playlist_owner_can_mutate_a_playlist(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $playlist = Playlist::create([
            'user_id' => $owner->id,
            'title' => 'Late bus home',
            'is_public' => true,
        ]);

        $this->actingAs($otherUser)
            ->withSession(['_token' => 'test-token'])
            ->delete(route('playlists.destroy', $playlist), ['_token' => 'test-token'])
            ->assertForbidden();

        $this->assertDatabaseHas('playlists', ['id' => $playlist->id]);
    }

    public function test_an_authenticated_user_can_create_a_playlist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post(route('playlists.store'), [
                '_token' => 'test-token',
                'title' => 'Một vòng thành phố',
                'description' => 'Những bài nghe lúc đi bộ.',
                'is_public' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('playlists', [
            'user_id' => $user->id,
            'title' => 'Một vòng thành phố',
            'is_public' => true,
        ]);
    }

    public function test_demo_catalogue_is_searchable_and_exposes_a_local_preview(): void
    {
        Storage::fake('public');
        $this->seed(ListenerDemoSeeder::class);

        $this->get(route('listen.search', ['q' => 'Nox']))
            ->assertOk()
            ->assertSee('Nox An')
            ->assertSee('Night signal');

        $track = Track::query()->where('slug', 'night-signal')->firstOrFail();

        $this->getJson(route('tracks.source', $track))
            ->assertOk()
            ->assertJsonPath('source', route('tracks.audio', $track));

        $this->get(route('tracks.audio', $track))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/wav');

        $this->withHeader('Range', 'bytes=0-1')
            ->get(route('tracks.audio', $track))
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes');

        Storage::disk('public')->assertExists('catalogue-demo/night-signal.wav');
    }

    public function test_topbar_suggestions_only_include_public_matches_with_playback_data(): void
    {
        $artist = Artist::create(['name' => 'Nox An', 'slug' => 'nox-an']);
        $track = $this->makeTrack($artist, ['title' => 'Night Signal']);
        $this->makeTrack($artist, ['title' => 'Hidden Signal', 'is_published' => false]);
        $album = Album::create(['artist_id' => $artist->id, 'title' => 'Signal Room', 'slug' => 'signal-room']);
        $album->tracks()->attach($track->id, ['sort_order' => 1]);

        $this->getJson(route('listen.search', ['q' => 'Signal', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'tracks')
            ->assertJsonPath('tracks.0.title', 'Night Signal')
            ->assertJsonPath('tracks.0.sourceUrl', route('tracks.audio', $track))
            ->assertJsonPath('albums.0.url', route('albums.show', $album))
            ->assertJsonMissing(['title' => 'Hidden Signal']);
    }

    public function test_guest_cannot_access_full_audio_and_source_never_exposes_storage_url(): void
    {
        $artist = Artist::create(['name' => 'Private media', 'slug' => 'private-media']);
        $track = $this->makeTrack($artist);
        $track->renditions()->create([
            'quality_label' => 'mp3_128', 'format' => 'mp3', 'bitrate_kbps' => 128,
            'file_key' => "processed/{$track->id}/mp3_128.mp3", 'file_size_bytes' => 1000,
        ]);

        $this->getJson(route('tracks.source', $track))->assertNotFound();
        $this->get(route('tracks.audio', $track))->assertNotFound();

        $track->renditions()->create([
            'quality_label' => 'preview_30', 'format' => 'mp3', 'bitrate_kbps' => 96,
            'file_key' => "processed/{$track->id}/preview_30.mp3", 'file_size_bytes' => 300,
        ]);

        $response = $this->getJson(route('tracks.source', $track))
            ->assertOk()
            ->assertJsonPath('source', route('tracks.audio', $track))
            ->assertJsonPath('quality', 'preview_30');
        $this->assertStringNotContainsString('processed/', $response->getContent());

        $this->actingAs(User::factory()->create())
            ->getJson(route('tracks.source', $track))
            ->assertOk()
            ->assertJsonPath('source', route('tracks.audio', [$track, 'quality' => 'auto']));
    }

    public function test_guest_range_request_streams_only_preview_object(): void
    {
        // Laravel streams the bytes itself only where stream-service is not running.
        config(['stream.web_audio_via_gateway' => false]);
        $artist = Artist::create(['name' => 'Range test', 'slug' => 'range-test']);
        $track = $this->makeTrack($artist);
        $track->renditions()->create([
            'quality_label' => 'mp3_128', 'format' => 'mp3', 'bitrate_kbps' => 128,
            'file_key' => "processed/{$track->id}/mp3_128.mp3", 'file_size_bytes' => 1000,
        ]);
        $previewKey = "processed/{$track->id}/preview_30.mp3";
        $track->renditions()->create([
            'quality_label' => 'preview_30', 'format' => 'mp3', 'bitrate_kbps' => 96,
            'file_key' => $previewKey, 'file_size_bytes' => 6,
        ]);
        config(['filesystems.disks.s3.bucket' => 'test-bucket']);
        $client = Mockery::mock();
        $client->shouldReceive('headObject')->once()
            ->with(['Bucket' => 'test-bucket', 'Key' => $previewKey])
            ->andReturn(['ContentLength' => 6]);
        $client->shouldReceive('getObject')->once()
            ->with(['Bucket' => 'test-bucket', 'Key' => $previewKey, 'Range' => 'bytes=0-2'])
            ->andReturn(['Body' => Utils::streamFor('abc')]);
        $disk = Mockery::mock();
        $disk->shouldReceive('getClient')->once()->andReturn($client);
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $this->withHeader('Range', 'bytes=0-2')
            ->get(route('tracks.audio', $track))
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-2/6')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_signed_in_listener_streams_full_rendition_at_saved_quality(): void
    {
        config(['stream.web_audio_via_gateway' => false]);
        $artist = Artist::create(['name' => 'Full stream', 'slug' => 'full-stream']);
        $track = $this->makeTrack($artist);
        $track->renditions()->create([
            'quality_label' => 'mp3_128', 'format' => 'mp3', 'bitrate_kbps' => 128,
            'file_key' => "processed/{$track->id}/mp3_128.mp3", 'file_size_bytes' => 1000,
        ]);
        $highKey = "processed/{$track->id}/mp3_320.mp3";
        $track->renditions()->create([
            'quality_label' => 'mp3_320', 'format' => 'mp3', 'bitrate_kbps' => 320,
            'file_key' => $highKey, 'file_size_bytes' => 2000,
        ]);
        $user = User::factory()->create();
        $user->preference()->create(['audio_quality' => 'high']);
        config(['filesystems.disks.s3.bucket' => 'test-bucket']);
        $client = Mockery::mock();
        $client->shouldReceive('headObject')->once()
            ->with(['Bucket' => 'test-bucket', 'Key' => $highKey])
            ->andReturn(['ContentLength' => 6]);
        $client->shouldReceive('getObject')->once()
            ->with(['Bucket' => 'test-bucket', 'Key' => $highKey, 'Range' => 'bytes=0-2'])
            ->andReturn(['Body' => Utils::streamFor('abc')]);
        $disk = Mockery::mock();
        $disk->shouldReceive('getClient')->once()->andReturn($client);
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $this->actingAs($user)->withHeader('Range', 'bytes=0-2')
            ->get(route('tracks.audio', $track))
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-2/6');
    }

    public function test_preview_registration_preserves_full_renditions_and_can_be_retried(): void
    {
        config(['app.internal_api_key' => 'preview-worker-key']);
        $artist = Artist::create(['name' => 'Preview worker', 'slug' => 'preview-worker']);
        $track = $this->makeTrack($artist);
        $fullKey = "processed/{$track->id}/mp3_128.mp3";
        $previewKey = "processed/{$track->id}/preview_30.mp3";
        $track->renditions()->create([
            'quality_label' => 'mp3_128', 'format' => 'mp3', 'bitrate_kbps' => 128,
            'file_key' => $fullKey, 'file_size_bytes' => 1000,
        ]);
        $payload = [
            'track_id' => $track->id, 'source_file_key' => $fullKey,
            'file_key' => $previewKey, 'size_bytes' => 300,
        ];

        $this->postJson('/api/internal/tracks/preview', $payload)->assertUnauthorized();
        $this->postJson('/api/internal/tracks/preview', $payload, ['X-Internal-Key' => 'preview-worker-key'])->assertOk();
        $this->postJson('/api/internal/tracks/preview', $payload, ['X-Internal-Key' => 'preview-worker-key'])->assertOk();

        $this->assertDatabaseCount('track_renditions', 2);
        $this->assertDatabaseHas('track_renditions', ['track_id' => $track->id, 'quality_label' => 'mp3_128', 'file_key' => $fullKey]);
        $this->assertDatabaseHas('track_renditions', ['track_id' => $track->id, 'quality_label' => 'preview_30', 'file_key' => $previewKey]);
    }

    public function test_preview_backfill_dry_run_lists_only_tracks_missing_preview(): void
    {
        $artist = Artist::create(['name' => 'Backfill test', 'slug' => 'backfill-test']);
        $track = $this->makeTrack($artist);
        $track->renditions()->create([
            'quality_label' => 'mp3_128', 'format' => 'mp3', 'bitrate_kbps' => 128,
            'file_key' => "processed/{$track->id}/mp3_128.mp3", 'file_size_bytes' => 1000,
        ]);

        Artisan::call('tracks:backfill-previews');
        $this->assertStringContainsString('Found 1 tracks', Artisan::output());
        $this->assertDatabaseCount('track_renditions', 1);
    }

    public function test_internal_stream_asset_lookup_selects_high_quality_without_exposing_it_publicly(): void
    {
        config(['app.internal_api_key' => 'stream-service-test-key']);

        $artist = Artist::create(['name' => 'Stream test', 'slug' => 'stream-test']);
        $track = $this->makeTrack($artist);
        $track->renditions()->create([
            'quality_label' => 'mp3_128',
            'format' => 'mp3',
            'bitrate_kbps' => 128,
            'file_key' => "processed/{$track->id}/mp3_128.mp3",
            'file_size_bytes' => 1_000,
        ]);
        $track->renditions()->create([
            'quality_label' => 'mp3_320',
            'format' => 'mp3',
            'bitrate_kbps' => 320,
            'file_key' => "processed/{$track->id}/mp3_320.mp3",
            'file_size_bytes' => 2_000,
        ]);

        $url = "/api/internal/stream/tracks/{$track->public_id}/asset?kind=audio&quality=high";

        $this->getJson($url)
            ->assertUnauthorized();

        $this->getJson($url, ['X-Internal-Key' => 'stream-service-test-key'])
            ->assertOk()
            ->assertJsonPath('key', "processed/{$track->id}/mp3_320.mp3")
            ->assertJsonPath('quality', 'mp3_320')
            ->assertJsonPath('content_type', 'audio/mpeg');
    }

    public function test_catalogue_search_accepts_unaccented_and_small_typo_queries(): void
    {
        if ($this->app['db']->connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Accent-insensitive and typo-tolerant search needs PostgreSQL unaccent and pg_trgm.');
        }

        Storage::fake('public');
        $this->seed(ListenerDemoSeeder::class);

        $this->get(route('listen.search', ['q' => 'cua so']))
            ->assertOk()
            ->assertSee('Cửa sổ mờ sương');

        $this->get(route('listen.search', ['q' => 'nght signal']))
            ->assertOk()
            ->assertSee('Night signal');
    }

    public function test_suggested_tracks_link_their_album_and_artist(): void
    {
        $artist = Artist::create(['name' => 'Nox An', 'slug' => 'nox-an']);
        $track = $this->makeTrack($artist, ['title' => 'Night Signal']);
        $album = Album::create(['artist_id' => $artist->id, 'title' => 'Signal Room', 'slug' => 'signal-room']);
        $album->tracks()->attach($track->id, ['sort_order' => 1]);

        $this->getJson(route('listen.search', ['q' => 'Signal', 'suggest' => 1]))
            ->assertOk()
            ->assertJsonPath('tracks.0.album', 'Signal Room')
            ->assertJsonPath('tracks.0.albumUrl', route('albums.show', $album))
            ->assertJsonPath('tracks.0.artistUrl', route('artists.show', $artist));
    }

    public function test_empty_search_page_offers_only_public_genres(): void
    {
        $artist = Artist::create(['name' => 'Genre test', 'slug' => 'genre-test']);
        $this->makeTrack($artist, ['genre' => 'Dream pop']);
        $this->makeTrack($artist, ['genre' => 'Dream pop']);
        $this->makeTrack($artist, ['genre' => 'Unreleased noise', 'is_published' => false]);

        $response = $this->get(route('listen.search'))->assertOk();

        $this->assertSame(['Dream pop'], $response->viewData('genres')->pluck('genre')->all());
        $this->assertSame(2, (int) $response->viewData('genres')->first()->tracks_count);
        $response->assertSee(route('listen.search', ['q' => 'Dream pop']), false)
            ->assertDontSee('Unreleased noise');
    }

    public function test_catalogue_lists_only_albums_with_public_tracks(): void
    {
        $artist = Artist::create(['name' => 'Album test', 'slug' => 'album-test']);
        $public = Album::create(['artist_id' => $artist->id, 'title' => 'Released album', 'slug' => 'released-album']);
        $public->tracks()->attach($this->makeTrack($artist)->id, ['sort_order' => 1]);
        $hidden = Album::create(['artist_id' => $artist->id, 'title' => 'Draft album', 'slug' => 'draft-album']);
        $hidden->tracks()->attach($this->makeTrack($artist, ['status' => 'processing'])->id, ['sort_order' => 1]);

        $this->get(route('listen.catalogue'))
            ->assertOk()
            ->assertSee('Released album')
            ->assertSee('data-play-context="album:'.$public->public_id.'"', false)
            ->assertDontSee('Draft album');
    }

    public function test_missing_pages_use_the_listener_error_page(): void
    {
        $this->get('/khong-ton-tai')
            ->assertNotFound()
            ->assertSee(__('errors.404.title'))
            ->assertSee(url('/search'), false);
    }

    public function test_album_page_exposes_one_ordered_playback_queue(): void
    {
        $artist = Artist::create(['name' => 'Queue test', 'slug' => 'queue-test']);
        $album = Album::create(['artist_id' => $artist->id, 'title' => 'Album queue', 'slug' => 'album-queue']);
        $firstTrack = $this->makeTrack($artist, ['title' => 'First track', 'slug' => 'first-track']);
        $secondTrack = $this->makeTrack($artist, ['title' => 'Second track', 'slug' => 'second-track']);
        $album->tracks()->attach([$firstTrack->id => ['sort_order' => 1], $secondTrack->id => ['sort_order' => 2]]);

        $this->get(route('albums.show', $album))
            ->assertOk()
            ->assertSee('data-track-queue', false)
            ->assertSeeInOrder(['First track', 'Second track']);
    }

    public function test_a_completed_playback_updates_the_count_and_is_idempotent(): void
    {
        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        $user = User::factory()->create();
        $artist = Artist::create(['name' => 'Listener test', 'slug' => 'listener-test']);
        $track = $this->makeTrack($artist, ['duration_seconds' => 192]);
        $payload = [
            'track_id' => $track->public_id,
            'played_duration_seconds' => 192,
            'completed' => true,
            'playback_id' => 'playback-completed-once',
        ];

        $this->actingAs($user)
            ->postJson(route('listening-history.store'), $payload)
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('listening-history.store'), $payload)
            ->assertNoContent();

        $this->assertSame(1, $track->fresh()->play_count);
        $this->assertDatabaseCount('user_play_logs', 1);
        $this->assertDatabaseHas('user_play_logs', [
            'user_id' => $user->id,
            'track_id' => $track->id,
            'completed' => true,
            // History needs neither the network address nor the browser.
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }

    public function test_library_history_displays_a_track_once_using_its_latest_log(): void
    {
        $user = User::factory()->create();
        $artist = Artist::create(['name' => 'History test', 'slug' => 'history-test']);
        $repeatedTrack = $this->makeTrack($artist, ['title' => 'Repeated track']);
        $otherTrack = $this->makeTrack($artist, ['title' => 'Other track']);

        $user->playLogs()->forceCreate([
            'track_id' => $repeatedTrack->id,
            'played_duration_seconds' => 80,
            'completed' => false,
            'created_at' => now()->subMinutes(3),
        ]);
        $user->playLogs()->forceCreate([
            'track_id' => $repeatedTrack->id,
            'played_duration_seconds' => 192,
            'completed' => true,
            'created_at' => now()->subMinutes(2),
        ]);
        $user->playLogs()->forceCreate([
            'track_id' => $otherTrack->id,
            'played_duration_seconds' => 30,
            'completed' => false,
            'created_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('library.index'));
        $history = $response->viewData('history');

        $response->assertOk();
        $this->assertCount(2, $history);
        $this->assertSame([$otherTrack->id, $repeatedTrack->id], $history->pluck('track_id')->all());
    }

    /** @param array<string, mixed> $overrides */
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
}
