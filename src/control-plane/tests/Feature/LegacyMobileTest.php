<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use App\Models\Media\Artist;
use App\Models\Media\Track;
use Database\Seeders\ListenerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_login_and_cannot_stream_audio(): void
    {
        $artist = Artist::create(['name' => 'Test Artist', 'slug' => 'test-artist']);
        $track = Track::create([
            'artist_id' => $artist->id,
            'title' => 'Private Stream',
            'slug' => 'private-stream',
            'status' => 'completed',
            'is_published' => true,
        ]);

        $this->get(route('legacy.index'))->assertOk()->assertSee('password', false);
        $this->get(route('legacy.audio', $track))->assertUnauthorized();
    }

    public function test_login_exposes_only_published_tracks_and_logout_ends_session(): void
    {
        $user = User::factory()->create();
        $artist = Artist::create(['name' => 'Test Artist', 'slug' => 'test-artist']);
        Track::create([
            'artist_id' => $artist->id, 'title' => 'Ready Song', 'slug' => 'ready-song',
            'status' => 'completed', 'is_published' => true,
        ]);
        Track::create([
            'artist_id' => $artist->id, 'title' => 'Hidden Song', 'slug' => 'hidden-song',
            'status' => 'completed', 'is_published' => false,
        ]);

        $this->post(route('legacy.login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('legacy.index'));

        $this->get(route('legacy.index'))
            ->assertOk()
            ->assertSee('Ready Song')
            ->assertSee('title=Ready%20Song&amp;artist=Test%20Artist', false)
            ->assertDontSee('Hidden Song');

        $this->post(route('legacy.logout'))->assertRedirect(route('legacy.index'));
        $this->get(route('legacy.index'))->assertOk()->assertDontSee('Ready Song');
    }

    public function test_demo_audio_uses_its_wav_content_type(): void
    {
        Storage::fake('public');
        $this->seed(ListenerDemoSeeder::class);
        $track = Track::where('slug', 'night-signal')->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->get(route('legacy.audio', $track))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/wav');
    }
}
