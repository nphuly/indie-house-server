<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use App\Services\Media\PlaybackTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GatewayPlaybackTest extends TestCase
{
    use RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyPath = tempnam(sys_get_temp_dir(), 'indie-ticket-key-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);
        file_put_contents($this->keyPath, $privateKey);
        config([
            'stream.jwt.private_key_path' => $this->keyPath,
            'stream.jwt.key_id' => 'stream-v1',
            'stream.jwt.issuer' => 'https://identity.indie-house.test',
            'stream.jwt.audience' => 'indie-house-mobile',
            'stream.web_audio_via_gateway' => true,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_guest_audio_redirects_to_the_gateway_with_a_signed_preview_ticket(): void
    {
        $track = $this->trackWith(['mp3_128', 'preview_30']);

        $response = $this->get(route('tracks.audio', $track))->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith($this->playPrefix(), $location);
        $this->assertStringNotContainsString('processed/', $location);

        $ticket = substr($location, strlen($this->playPrefix()));
        $claims = $this->verifiedClaims($ticket);
        $this->assertSame('playback', $claims['token_use']);
        $this->assertSame($track->public_id, $claims['trk']);
        $this->assertSame('preview', $claims['q']);
        $this->assertMatchesRegularExpression('/\Aguest:[a-f0-9]{16}\z/', $claims['sub']);
        $this->assertSame('https://identity.indie-house.test', $claims['iss']);
        $this->assertSame('indie-house-mobile', $claims['aud']);
        $this->assertSame(3600, $claims['exp'] - $claims['iat']);
    }

    public function test_listener_ticket_carries_the_account_and_chosen_quality(): void
    {
        $track = $this->trackWith(['mp3_128', 'mp3_320']);
        $user = User::factory()->create();
        $user->preference()->create(['audio_quality' => 'high']);

        $saved = $this->ticketClaims($this->actingAs($user)->get(route('tracks.audio', $track)));
        $this->assertSame((string) $user->getKey(), $saved['sub']);
        $this->assertSame('high', $saved['q']);

        $asked = $this->ticketClaims($this->actingAs($user)->get(route('tracks.audio', [$track, 'quality' => 'standard'])));
        $this->assertSame('standard', $asked['q']);
    }

    public function test_no_ticket_is_signed_for_audio_that_does_not_exist(): void
    {
        $track = $this->trackWith(['mp3_128']);
        $this->get(route('tracks.audio', $track))->assertNotFound();

        $hidden = $this->trackWith(['mp3_128', 'preview_30'], ['is_published' => false]);
        $this->actingAs(User::factory()->create())->get(route('tracks.audio', $hidden))->assertNotFound();
    }

    public function test_legacy_audio_redirects_a_signed_in_listener_to_the_gateway(): void
    {
        $track = $this->trackWith(['mp3_128', 'mp3_320']);

        $claims = $this->ticketClaims($this->actingAs(User::factory()->create())->get(route('legacy.audio', $track)));
        $this->assertSame('auto', $claims['q']);
    }

    public function test_a_playback_ticket_is_not_a_mobile_access_token(): void
    {
        $track = $this->trackWith(['mp3_128']);
        $user = User::factory()->create();
        $ticket = app(PlaybackTicketService::class)->issue($track, 'auto', (string) $user->getKey());

        $this->getJson('/api/v1/mobile/catalogue', ['Authorization' => "Bearer {$ticket}"])->assertUnauthorized();
    }

    public function test_internal_lookup_serves_the_preview_and_rejects_unknown_qualities(): void
    {
        config(['app.internal_api_key' => 'stream-service-test-key']);
        $track = $this->trackWith(['mp3_128', 'preview_30']);
        $url = "/api/internal/stream/tracks/{$track->public_id}/asset?kind=audio&quality=";
        $headers = ['X-Internal-Key' => 'stream-service-test-key'];

        $this->getJson($url.'preview', $headers)
            ->assertOk()
            ->assertJsonPath('key', "processed/{$track->id}/preview_30.mp3")
            ->assertJsonPath('quality', 'preview_30');
        $this->getJson($url.'ultra', $headers)->assertUnprocessable();
    }

    public function test_changes_that_affect_playback_tell_the_gateway_after_they_are_saved(): void
    {
        config(['stream.cache_invalidation' => true]);
        $track = $this->trackWith([]);
        $published = [];
        $connection = Mockery::mock();
        $connection->shouldReceive('publish')->andReturnUsing(function (string $channel, string $message) use (&$published): int {
            $published[] = "{$channel} {$message}";

            return 1;
        });
        Redis::shouldReceive('connection')->with('stream')->andReturn($connection);

        $track->update(['title' => 'A new title']);
        $this->assertSame([], $published, 'a title change does not affect playback');

        Queue::fake(); // deleting the track queues its S3 cleanup
        $track->update(['is_published' => false]);
        $track->renditions()->create([
            'quality_label' => 'mp3_320', 'format' => 'mp3', 'bitrate_kbps' => 320,
            'file_key' => "processed/{$track->id}/mp3_320.mp3", 'file_size_bytes' => 10,
        ]);
        $track->delete();

        $this->assertSame(array_fill(0, 3, "stream:invalidate {$track->public_id}"), $published);
    }

    public function test_a_redis_outage_does_not_block_track_edits(): void
    {
        config(['stream.cache_invalidation' => true]);
        $track = $this->trackWith([]);
        Redis::shouldReceive('connection')->with('stream')->andThrow(new RuntimeException('Redis is down'));

        $track->update(['is_published' => false]);

        $this->assertFalse((bool) $track->fresh()->is_published);
    }

    /** @param  list<string>  $labels */
    private function trackWith(array $labels, array $overrides = []): Track
    {
        $artist = Artist::firstOrCreate(['slug' => 'gateway-test'], ['name' => 'Gateway test']);
        $track = Track::create([
            'artist_id' => $artist->id,
            'title' => 'Track '.str()->random(8),
            'slug' => str()->random(10),
            'duration_seconds' => 192,
            'status' => 'completed',
            'is_published' => true,
            ...$overrides,
        ]);
        foreach ($labels as $label) {
            $track->renditions()->create([
                'quality_label' => $label, 'format' => 'mp3', 'bitrate_kbps' => 128,
                'file_key' => "processed/{$track->id}/{$label}.mp3", 'file_size_bytes' => 10,
            ]);
        }

        return $track;
    }

    /** @return array<string, mixed> */
    private function ticketClaims($response): array
    {
        $location = (string) $response->assertRedirect()->headers->get('Location');

        return $this->verifiedClaims(substr($location, strlen($this->playPrefix())));
    }

    private function playPrefix(): string
    {
        return url('/api/v1/stream/v1/play').'/';
    }

    /** @return array<string, mixed> */
    private function verifiedClaims(string $ticket): array
    {
        [$header, $payload, $signature] = explode('.', $ticket);
        $privateKey = openssl_pkey_get_private((string) file_get_contents($this->keyPath));
        $publicKey = openssl_pkey_get_details($privateKey)['key'];
        $this->assertSame(1, openssl_verify("{$header}.{$payload}", $this->decode($signature), $publicKey, OPENSSL_ALGO_SHA256));
        $this->assertSame('stream-v1', json_decode($this->decode($header), true)['kid']);

        return json_decode($this->decode($payload), true);
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
