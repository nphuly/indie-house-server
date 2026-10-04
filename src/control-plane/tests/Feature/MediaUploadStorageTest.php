<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Filament\Resources\Artists\Pages\EditArtist;
use App\Filament\Resources\Tracks\Pages\CreateTrack;
use App\Jobs\ProcessTrackJob;
use App\Models\Auth\User;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use App\Support\PendingUpload;
use ArrayObject;
use Filament\Facades\Filament;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaUploadStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_calls_go_to_the_internal_endpoint_while_urls_stay_public(): void
    {
        $sent = $this->fakeS3(internalEndpoint: 'http://minio.internal:9000');
        $disk = Storage::disk('s3');

        $this->assertTrue($disk->exists('artists/1/avatar.jpg'));

        $this->assertCount(1, $sent);
        $this->assertSame('http://minio.internal:9000/media/artists/1/avatar.jpg', (string) $sent[0]->getUri());
        // The signature covers the public host, which MinIO sees in the Host header.
        $this->assertSame('objects.example.test', $sent[0]->getHeaderLine('Host'));
        $this->assertMatchesRegularExpression('/SignedHeaders=[^,]*\bhost\b/', $sent[0]->getHeaderLine('Authorization'));

        $download = $disk->temporaryUrl('artists/1/avatar.jpg', now()->addMinutes(5));
        $upload = $disk->temporaryUploadUrl('livewire-tmp/song.flac', now()->addMinutes(5));

        $this->assertStringStartsWith('https://objects.example.test/media/artists/1/avatar.jpg?', $download);
        $this->assertStringStartsWith('https://objects.example.test/media/livewire-tmp/song.flac?', $upload['url']);
        $this->assertCount(1, $sent, 'Presigning must not send anything.');
    }

    public function test_without_an_internal_endpoint_api_calls_use_the_public_endpoint(): void
    {
        $sent = $this->fakeS3(internalEndpoint: null);

        Storage::disk('s3')->exists('artists/1/avatar.jpg');

        $this->assertSame('https://objects.example.test/media/artists/1/avatar.jpg', (string) $sent[0]->getUri());
    }

    public function test_a_pending_upload_is_removed_only_after_its_record_commits(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('tracks/_pending/song.flac', 'audio');

        DB::beginTransaction();
        PendingUpload::promote('tracks/_pending/song.flac', 'tracks/raw/rolled-back/song.flac');
        DB::rollBack();

        Storage::disk('s3')->assertExists('tracks/_pending/song.flac');

        DB::transaction(fn () => PendingUpload::promote('tracks/_pending/song.flac', 'tracks/raw/kept/song.flac'));

        Storage::disk('s3')->assertMissing('tracks/_pending/song.flac');
        $this->assertSame('audio', Storage::disk('s3')->get('tracks/raw/kept/song.flac'));
    }

    public function test_promoting_fails_loudly_when_storage_cannot_copy(): void
    {
        Storage::fake('s3');

        $this->expectException(RuntimeException::class);

        PendingUpload::promote('tracks/_pending/missing.flac', 'tracks/raw/missing.flac');
    }

    public function test_creating_a_track_moves_its_upload_out_of_pending_and_queues_processing(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $this->actingAsAdmin();
        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox']);

        Livewire::test(CreateTrack::class)
            ->fillForm($this->trackForm($artist))
            ->call('create')
            ->assertHasNoFormErrors();

        $track = Track::sole();
        $this->assertMatchesRegularExpression("#^tracks/raw/\\d{4}/\\d{2}/{$track->id}/[^/]+\\.flac$#", $track->raw_file_key);
        Storage::disk('s3')->assertExists($track->raw_file_key);
        $this->assertSame([], Storage::disk('s3')->files('tracks/_pending'));
        Queue::assertPushed(ProcessTrackJob::class, fn (ProcessTrackJob $job): bool => $job->trackId === $track->id);
    }

    public function test_a_track_is_not_created_when_its_upload_cannot_be_moved(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $this->actingAsAdmin();
        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox']);
        // Storage loses the pending file between the form save and the move.
        Track::creating(function (): void {
            Storage::disk('s3')->delete(Storage::disk('s3')->files('tracks/_pending'));
        });

        Livewire::test(CreateTrack::class)
            ->fillForm($this->trackForm($artist))
            ->call('create')
            ->assertNotified(__('general.upload_not_saved.title'));

        $this->assertSame(0, Track::count());
        Queue::assertNothingPushed();
    }

    public function test_saving_an_artist_keeps_images_that_storage_did_not_confirm(): void
    {
        Storage::fake('s3');
        $this->actingAsAdmin();
        $artist = Artist::create([
            'name' => 'Nox',
            'slug' => 'nox',
            'avatar' => 'artists/1/avatar.jpg',
            'cover_image' => 'artists/1/cover.jpg',
        ]);

        Livewire::test(EditArtist::class, ['record' => $artist->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $artist->refresh();
        $this->assertSame('artists/1/avatar.jpg', $artist->avatar);
        $this->assertSame('artists/1/cover.jpg', $artist->cover_image);
    }

    public function test_repair_moves_stranded_uploads_to_the_keys_records_point_at(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Storage::fake('public');
        config(['filament.default_filesystem_disk' => 'local']);
        Queue::fake();
        $artist = Artist::create(['name' => 'Nox', 'slug' => 'nox']);
        $artist->update(['avatar' => "artists/{$artist->id}/avatar.jpg", 'cover_image' => "artists/{$artist->id}/cover.jpg"]);
        $track = Track::create(['artist_id' => $artist->id, 'title' => 'Mây', 'slug' => 'may', 'genre' => 'indie', 'status' => 'failed']);
        $track->update(['raw_file_key' => "tracks/raw/2026/09/{$track->id}/may.flac"]);
        $lost = Track::create(['artist_id' => $artist->id, 'title' => 'Lost', 'slug' => 'lost', 'genre' => 'indie', 'raw_file_key' => 'tracks/raw/2026/09/x/lost.flac']);
        Storage::disk('s3')->put('tracks/_pending/may.flac', 'audio');
        Storage::disk('s3')->put('artists/_pending/avatar.jpg', 'avatar');
        Storage::disk('local')->put('artists/_pending/cover.jpg', 'cover');
        $listener = User::factory()->create();
        $listener->forceFill(['avatar_path' => "avatars/{$listener->id}/me.png"])->save();
        Storage::disk('public')->put($listener->avatar_path, 'me');
        // Livewire's temporary name embeds the browser's file name.
        Storage::disk('s3')->put('livewire-tmp/abc'.str_replace('/', '_', '-meta'.base64_encode('Mây (master).flac').'-').'.flac', 'orphan');

        $this->artisan('media:repair-uploads', ['--dry-run' => true])
            ->expectsOutputToContain('would move tracks/_pending/may.flac')
            ->assertSuccessful();
        Storage::disk('s3')->assertMissing($track->raw_file_key);

        $this->artisan('media:repair-uploads')
            ->expectsOutputToContain("track {$track->id}")
            ->expectsOutputToContain('Mây (master).flac')
            ->assertSuccessful();

        $this->assertSame('audio', Storage::disk('s3')->get($track->raw_file_key));
        $this->assertSame('avatar', Storage::disk('s3')->get($artist->avatar));
        $this->assertSame('cover', Storage::disk('s3')->get($artist->cover_image));
        Storage::disk('s3')->assertMissing(['tracks/_pending/may.flac', 'artists/_pending/avatar.jpg']);
        Storage::disk('local')->assertMissing('artists/_pending/cover.jpg');
        $this->assertSame('me', Storage::disk('s3')->get($listener->avatar_path));
        Storage::disk('public')->assertMissing($listener->avatar_path);
        Storage::disk('s3')->assertMissing($lost->raw_file_key);
        Queue::assertPushed(ProcessTrackJob::class, fn (ProcessTrackJob $job): bool => $job->trackId === $track->id);
        Queue::assertNotPushed(ProcessTrackJob::class, fn (ProcessTrackJob $job): bool => $job->trackId === $lost->id);
    }

    /**
     * @return ArrayObject<int, RequestInterface> requests the S3 client sends, filled as they happen
     */
    private function fakeS3(?string $internalEndpoint): ArrayObject
    {
        $sent = new ArrayObject;

        config(['filesystems.disks.s3' => [
            'driver' => 's3',
            'key' => 'key',
            'secret' => 'secret',
            'region' => 'us-east-1',
            'bucket' => 'media',
            'endpoint' => 'https://objects.example.test',
            'internal_endpoint' => $internalEndpoint,
            'use_path_style_endpoint' => true,
            'http_handler' => function (RequestInterface $request) use ($sent) {
                $sent[] = $request;

                return Create::promiseFor(new Response(200));
            },
        ]]);
        Storage::forgetDisk('s3');

        return $sent;
    }

    /** @return array<string, mixed> */
    private function trackForm(Artist $artist): array
    {
        return [
            'artist_id' => $artist->id,
            'title' => 'Mây',
            'slug' => 'may',
            'genre' => 'indie',
            'raw_file_key' => UploadedFile::fake()->create('may.flac', 64, 'audio/flac'),
        ];
    }

    private function actingAsAdmin(): void
    {
        Role::findOrCreate(Roles::SUPER_ADMIN->value, 'web');
        $admin = User::factory()->create();
        $admin->assignRole(Roles::SUPER_ADMIN->value);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
    }
}
