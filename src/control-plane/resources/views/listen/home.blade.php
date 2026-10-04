@extends('listen.layouts.app', ['title' => __('listen.home.title')])

@use('App\Support\Icon')
@use('App\Support\PlayerTrack')

@section('content')
    @php
        $topQueue = PlayerTrack::queue($tracks);
        $spotlight = $tracks->first();
        $spotlightPayload = $topQueue->first();
        $onAirArtists = $tracks->pluck('artist')->unique('id')->values();
        $recentQueue = PlayerTrack::queue($recentTracks);
        $quickPlaylists = auth()->check() ? $userPlaylists->take($recentTracks->isNotEmpty() ? 3 : 4) : collect();
        $quickAlbums = $albums->take(8 - $quickPlaylists->count() - ($recentTracks->isNotEmpty() ? 1 : 0));
    @endphp

    <header class="home-head">
        <h1 class="home-head__title" data-greeting data-name="{{ auth()->user()?->name }}">{{ __('listen.home.hello') }}</h1>

        @if ($quickPlaylists->isNotEmpty() || $quickAlbums->isNotEmpty() || $recentTracks->isNotEmpty())
            <div class="quick-grid">
                @if ($recentTracks->isNotEmpty())
                    @include('listen.components.quick-tile', ['label' => __('listen.home.recent'), 'url' => route('library.index').'#history', 'image' => null, 'icon' => 'history', 'seed' => 'history', 'queue' => $recentQueue, 'context' => 'history'])
                @endif
                @foreach ($quickPlaylists as $playlist)
                    @include('listen.components.quick-tile', ['label' => $playlist->title, 'url' => route('playlists.show', $playlist), 'image' => null, 'icon' => null, 'seed' => $playlist->public_id, 'queue' => null, 'context' => null])
                @endforeach
                @foreach ($quickAlbums as $album)
                    @include('listen.components.quick-tile', ['label' => $album->title, 'url' => route('albums.show', $album), 'image' => $album->cover_url, 'icon' => null, 'seed' => $album->title, 'queue' => PlayerTrack::queue($album->tracks, $album), 'context' => 'album:'.$album->public_id])
                @endforeach
            </div>
        @endif
    </header>

    @if ($spotlight)
        <section class="spotlight" aria-labelledby="spotlight-title">
            @include('listen.components.hero-art', ['class' => 'spotlight__photo', 'creditClass' => 'spotlight__credit', 'sizes' => '(max-width: 767px) 100vw, 50vw', 'eager' => true])
            <div class="spotlight__inner">
                @include('listen.components.art', ['image' => $spotlightPayload['artwork'], 'alt' => $spotlightPayload['album'] ? __('listen.home.cover_of', ['album' => $spotlightPayload['album']]) : '', 'seed' => $spotlightPayload['album'] ?? $spotlight->title, 'glyph' => $spotlight->title, 'class' => 'spotlight__art', 'eager' => true])
                <div class="spotlight__copy">
                    <p class="tag">{{ __('listen.home.most_played') }}</p>
                    <h2 class="spotlight__title" id="spotlight-title">{{ $spotlight->title }}</h2>
                    <p class="spotlight__meta">
                        <a href="{{ $spotlightPayload['artistUrl'] }}">{{ $spotlightPayload['artist'] }}</a>
                        <span class="dot" aria-hidden="true"></span>
                        <span class="mono">{{ \App\Support\Duration::plays($spotlight->play_count) }}</span>
                    </p>
                    <div class="spotlight__actions">
                        <button class="play-button" type="button" data-play-track data-track='{{ PlayerTrack::json($spotlightPayload) }}' data-track-queue='{{ PlayerTrack::json($topQueue) }}' data-play-context="home:top" data-play-context-label="{{ __('listen.home.popular') }}" aria-label="{{ __('listen.play_label', ['label' => $spotlight->title]) }}">
                            {{ Icon::svg('play', 'play-button__play') }}{{ Icon::svg('pause', 'play-button__pause') }}
                        </button>
                        @if ($spotlightPayload['albumUrl'])
                            <a class="button button--outline" href="{{ $spotlightPayload['albumUrl'] }}">{{ __('listen.home.open_album') }}</a>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($albums->isNotEmpty())
        <section class="listen-section" aria-labelledby="new-albums">
            <div class="section-heading">
                <h2 id="new-albums">{{ __('listen.home.new_albums') }}</h2>
                <a href="{{ route('listen.catalogue') }}">{{ __('listen.see_all') }}</a>
            </div>
            <div class="shelf" data-shelf>
                @foreach ($albums as $album)
                    @include('listen.components.media-card', ['item' => $album])
                @endforeach
            </div>
        </section>
    @elseif ($tracks->isEmpty())
        <div class="listen-section">
            @include('listen.components.empty-state', ['title' => __('listen.home.empty_title'), 'message' => __('listen.home.empty_body'), 'icon' => 'disc'])
        </div>
    @endif

    @auth
        <section class="listen-section" aria-labelledby="recent-heading">
            <div class="section-heading">
                <h2 id="recent-heading">{{ __('listen.home.recent') }}</h2>
                @if ($recentTracks->isNotEmpty())
                    <a href="{{ route('library.index') }}#history">{{ __('listen.see_all') }}</a>
                @endif
            </div>
            @if ($recentTracks->isNotEmpty())
                @include('listen.components.track-list', ['tracks' => $recentTracks, 'queue' => $recentQueue, 'context' => 'history', 'contextLabel' => __('listen.home.recent'), 'numbered' => false, 'header' => false])
            @else
                @include('listen.components.empty-state', ['title' => __('listen.home.history_empty_title'), 'message' => __('listen.home.history_empty_body'), 'icon' => 'history'])
            @endif
        </section>
    @endauth

    @if ($tracks->isNotEmpty())
        <section class="listen-section" aria-labelledby="chart-heading">
            <div class="section-heading">
                <h2 id="chart-heading">{{ __('listen.home.popular') }}</h2>
            </div>
            @include('listen.components.track-list', ['tracks' => $tracks, 'queue' => $topQueue, 'context' => 'home:top', 'contextLabel' => __('listen.home.popular')])
        </section>
    @endif

    @if ($onAirArtists->isNotEmpty())
        <section class="listen-section" aria-labelledby="artists-heading">
            <div class="section-heading">
                <h2 id="artists-heading">{{ __('listen.home.artists') }}</h2>
                <a href="{{ route('listen.catalogue') }}">{{ __('listen.see_all') }}</a>
            </div>
            <div class="shelf" data-shelf>
                @foreach ($onAirArtists as $artist)
                    @include('listen.components.media-card', ['item' => $artist, 'kind' => 'artist'])
                @endforeach
            </div>
        </section>
    @endif
@endsection
