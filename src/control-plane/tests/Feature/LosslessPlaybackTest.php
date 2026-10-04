<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use App\Services\Auth\MobileTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class LosslessPlaybackTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyPath = tempnam(sys_get_temp_dir(), 'indie-lossless-key-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        file_put_contents($this->keyPath, $privateKey);
        config([
            'stream.jwt.private_key_path' => $this->keyPath,
            'stream.jwt.key_id' => 'stream-v1',
            'stream.jwt.issuer' => 'https://identity.indie-house.test',
            'stream.jwt.audience' => 'indie-house-mobile',
            'stream.auth_rate_limit.store' => 'array',
            'stream.web_audio_via_gateway' => true,
            'app.internal_api_key' => 'worker-key',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_lossless_serves_the_flac_and_falls_back_to_the_best_mp3(): void
    {
        $withFlac = $this->track(['mp3_128', 'mp3_320', 'lossless']);
        $mp3Only = $this->track(['mp3_128', 'mp3_320']);
        $headers = ['X-Internal-Key' => 'worker-key'];

        $this->getJson($this->assetUrl($withFlac, 'lossless'), $headers)
            ->assertOk()
            ->assertJsonPath('key', "processed/{$withFlac->id}/lossless.flac")
            ->assertJsonPath('content_type', 'audio/flac')
            ->assertJsonPath('quality', 'lossless');
        $this->getJson($this->assetUrl($mp3Only, 'lossless'), $headers)
            ->assertOk()
            ->assertJsonPath('quality', 'mp3_320')
            ->assertJsonPath('content_type', 'audio/mpeg');
        $this->getJson($this->assetUrl($withFlac, 'high'), $headers)->assertJsonPath('quality', 'mp3_320');
    }

    public function test_web_tickets_carry_lossless_only_for_a_granted_listener(): void
    {
        $track = $this->track(['mp3_128', 'mp3_320', 'lossless']);
        $granted = $this->listener(lossless: true);
        $granted->preference()->create(['audio_quality' => 'lossless']);
        $revoked = $this->listener(lossless: false);
        $revoked->preference()->create(['audio_quality' => 'lossless']);

        $this->assertSame('lossless', $this->ticketQuality($this->actingAs($granted)->get(route('tracks.audio', $track))));
        $this->assertSame('high', $this->ticketQuality($this->actingAs($revoked)->get(route('tracks.audio', $track))));
        $this->assertSame('high', $this->ticketQuality($this->actingAs($revoked)->get(route('tracks.audio', [$track, 'quality' => 'lossless']))));
    }

    public function test_only_a_granted_listener_can_choose_lossless(): void
    {
        $plain = $this->listener(lossless: false);
        $this->actingAs($plain)->get(route('settings.show'))->assertOk()->assertDontSee('value="lossless"', false);
        $this->actingAs($plain)->put(route('settings.playback'), ['audio_quality' => 'lossless', 'autoplay' => 1])
            ->assertSessionHasErrors('audio_quality');

        $granted = $this->listener(lossless: true);
        $this->actingAs($granted)->get(route('settings.show'))->assertOk()->assertSee('value="lossless"', false);
        $this->actingAs($granted)->put(route('settings.playback'), ['audio_quality' => 'lossless', 'autoplay' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('lossless', $granted->fresh()->preference->audio_quality);
    }

    public function test_mobile_preferences_offer_lossless_only_when_granted(): void
    {
        foreach ([false, true] as $lossless) {
            $user = $this->listener(lossless: $lossless);
            $token = app(MobileTokenService::class)->issue($user)['access_token'];
            $headers = ['Authorization' => "Bearer {$token}"];
            $claims = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
            $this->assertSame($lossless, $claims['lossless']);

            $this->getJson('/api/v1/mobile/preferences', $headers)->assertOk()->assertJsonPath('data.lossless_available', $lossless);
            $response = $this->patchJson('/api/v1/mobile/preferences', ['audio_quality' => 'lossless'], $headers);
            $lossless ? $response->assertOk()->assertJsonPath('data.audio_quality', 'lossless')
                : $response->assertUnprocessable()->assertJsonValidationErrors('audio_quality');
        }
    }

    public function test_the_worker_adds_a_flac_to_a_processed_track_and_nothing_else(): void
    {
        $track = $this->track(['mp3_128', 'mp3_320'], ['raw_file_key' => 'tracks/raw/master.flac']);
        $payload = [
            'track_id' => $track->id, 'source_file_key' => 'tracks/raw/master.flac',
            'file_key' => "processed/{$track->id}/lossless.flac", 'size_bytes' => 30_000_000, 'bitrate_kbps' => 980,
        ];
        $headers = ['X-Internal-Key' => 'worker-key'];

        $this->postJson('/api/internal/tracks/lossless', [...$payload, 'file_key' => 'processed/elsewhere.flac'], $headers)->assertUnprocessable();
        $this->postJson('/api/internal/tracks/lossless', [...$payload, 'source_file_key' => 'tracks/raw/other.flac'], $headers)->assertUnprocessable();
        $this->postJson('/api/internal/tracks/lossless', $payload, $headers)->assertOk();
        $this->postJson('/api/internal/tracks/lossless', $payload, $headers)->assertOk();

        $this->assertSame(3, $track->renditions()->count());
        $this->assertDatabaseHas('track_renditions', [
            'track_id' => $track->id, 'quality_label' => 'lossless', 'format' => 'flac', 'bitrate_kbps' => 980,
        ]);
    }

    public function test_backfill_queues_only_lossless_uploads_without_a_flac(): void
    {
        $wav = $this->track(['mp3_128'], ['raw_file_key' => 'tracks/raw/a/Master.WAV']);
        $this->track(['mp3_128'], ['raw_file_key' => 'tracks/raw/b/demo.mp3']);
        $this->track(['mp3_128', 'lossless'], ['raw_file_key' => 'tracks/raw/c/done.flac']);
        $this->track(['mp3_128'], ['raw_file_key' => 'tracks/raw/d/draft.flac', 'status' => 'processing']);
        $queued = [];
        $connection = Mockery::mock();
        $connection->shouldReceive('lpush')->andReturnUsing(function (string $queue, string $job) use (&$queued): int {
            $queued[] = json_decode($job, true);

            return 1;
        });
        Redis::shouldReceive('connection')->with('media_worker')->andReturn($connection);

        $this->artisan('tracks:backfill-lossless')->expectsOutputToContain('Found 1')->assertSuccessful();
        $this->assertSame([], $queued);
        $this->artisan('tracks:backfill-lossless --dispatch')->assertSuccessful();

        $this->assertSame([[
            'kind' => 'lossless_backfill', 'track_id' => $wav->id, 'raw_file_key' => 'tracks/raw/a/Master.WAV', 'attempt' => 0,
        ]], $queued);
    }

    private function listener(bool $lossless): User
    {
        $user = User::factory()->create();
        $user->streamAccess()->create(['high_quality_enabled' => true, 'lossless_enabled' => $lossless]);

        return $user;
    }

    /** @param  list<string>  $labels */
    private function track(array $labels, array $overrides = []): Track
    {
        $artist = Artist::firstOrCreate(['slug' => 'lossless-test'], ['name' => 'Lossless test']);
        $track = Track::create([
            'artist_id' => $artist->id, 'title' => 'Track '.str()->random(8), 'slug' => str()->random(10),
            'duration_seconds' => 200, 'status' => 'completed', 'is_published' => true, ...$overrides,
        ]);
        foreach ($labels as $label) {
            $flac = $label === 'lossless';
            $track->renditions()->create([
                'quality_label' => $label, 'format' => $flac ? 'flac' : 'mp3', 'bitrate_kbps' => $flac ? 1000 : 128,
                'file_key' => "processed/{$track->id}/{$label}.".($flac ? 'flac' : 'mp3'), 'file_size_bytes' => 10,
            ]);
        }

        return $track;
    }

    private function assetUrl(Track $track, string $quality): string
    {
        return "/api/internal/stream/tracks/{$track->public_id}/asset?kind=audio&quality={$quality}";
    }

    private function ticketQuality($response): string
    {
        $location = (string) $response->assertRedirect()->headers->get('Location');
        $payload = explode('.', substr($location, strlen(url('/api/v1/stream/v1/play').'/')))[1];

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['q'];
    }
}
