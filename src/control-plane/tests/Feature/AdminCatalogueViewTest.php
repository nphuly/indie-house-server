<?php

namespace Tests\Feature;

use App\Enums\Roles;
use App\Filament\Resources\Artists\Pages\ViewArtist;
use App\Filament\Resources\Tracks\Pages\ViewTrack;
use App\Filament\Resources\Tracks\TrackResource;
use App\Models\Auth\User;
use App\Models\Media\Album;
use App\Models\Media\Artist;
use App\Models\Media\Playlist;
use App\Models\Media\Track;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminCatalogueViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_track_is_named_in_its_breadcrumbs_and_found_by_global_search(): void
    {
        $this->actingAsAdmin();
        $track = $this->makeTrack(Artist::create(['name' => 'Nox An', 'slug' => 'nox-an']), ['title' => 'Night Signal']);

        $breadcrumbs = Livewire::test(ViewTrack::class, ['record' => $track->getRouteKey()])
            ->assertOk()
            ->instance()
            ->getBreadcrumbs();

        $this->assertSame([TrackResource::getBreadcrumb(), 'Night Signal'], array_slice(array_values($breadcrumbs), 0, 2));
        $this->assertCount(1, TrackResource::getGlobalSearchResults('Night'));
    }

    public function test_the_artist_page_lists_popular_tracks_albums_and_public_playlists(): void
    {
        $this->actingAsAdmin();
        $artist = Artist::create(['name' => 'Nox An', 'slug' => 'nox-an']);
        $hit = $this->makeTrack($artist, ['title' => 'Night Signal', 'play_count' => 90]);
        $this->makeTrack($artist, ['title' => 'Quiet Signal', 'play_count' => 2]);
        $album = Album::create(['artist_id' => $artist->id, 'title' => 'Signal Room', 'slug' => 'signal-room']);
        $album->tracks()->attach($hit->id, ['sort_order' => 1]);
        $owner = User::factory()->create();
        Playlist::create(['user_id' => $owner->id, 'title' => 'Late bus home', 'is_public' => true])
            ->tracks()->attach($hit->id, ['sort_order' => 1]);
        Playlist::create(['user_id' => $owner->id, 'title' => 'Diary mix', 'is_public' => false])
            ->tracks()->attach($hit->id, ['sort_order' => 1]);

        Livewire::test(ViewArtist::class, ['record' => $artist->getRouteKey()])
            ->assertOk()
            ->assertSeeInOrder(['Night Signal', 'Quiet Signal'])
            ->assertSee('Signal Room')
            ->assertSee('Late bus home')
            ->assertDontSee('Diary mix');
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

    private function actingAsAdmin(): void
    {
        Role::findOrCreate(Roles::SUPER_ADMIN->value, 'web');
        $admin = User::factory()->create();
        $admin->assignRole(Roles::SUPER_ADMIN->value);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
    }
}
