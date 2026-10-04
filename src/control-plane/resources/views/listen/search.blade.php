@extends('listen.layouts.app', ['title' => $query !== '' ? __('listen.search.title_query', ['query' => $query]) : __('listen.search.title')])

@use('App\Support\Icon')
@use('App\Support\PlayerTrack')
@use('Illuminate\Support\Str')

@section('content')
    @php
        $hasResults = $tracks->isNotEmpty() || $artists->isNotEmpty() || $albums->isNotEmpty();
        $trackQueue = PlayerTrack::queue($tracks);
        $top = null;

        if ($query !== '' && $hasResults) {
            // Prefer an artist whose name contains the query, as a listener
            // typing a name usually wants that artist rather than one song.
            $needle = Str::lower(Str::ascii($query));
            $topArtist = $artists->first(fn ($artist) => str_contains(Str::lower(Str::ascii($artist->name)), $needle));

            if ($topArtist) {
                $artistQueue = PlayerTrack::queue($tracks->where('artist_id', $topArtist->id));
                $top = ['kind' => __('listen.search.kind_artist'), 'title' => $topArtist->name, 'url' => route('artists.show', $topArtist), 'image' => $topArtist->avatar_url ?? $topArtist->cover_url, 'artist' => true, 'queue' => $artistQueue, 'context' => 'artist:'.$topArtist->public_id, 'byline' => null];
            } elseif ($tracks->isNotEmpty()) {
                $first = $trackQueue->first();
                $top = ['kind' => __('listen.search.kind_song'), 'title' => $first['title'], 'url' => $first['albumUrl'] ?? $first['artistUrl'], 'image' => $first['artwork'], 'artist' => false, 'queue' => $trackQueue, 'context' => 'search:'.$query, 'byline' => $first['artist']];
            } elseif ($albums->isNotEmpty()) {
                $album = $albums->first();
                $top = ['kind' => __('listen.search.kind_album'), 'title' => $album->title, 'url' => route('albums.show', $album), 'image' => $album->cover_url, 'artist' => false, 'queue' => collect(), 'context' => null, 'byline' => $album->artist->name];
            } else {
                $artist = $artists->first();
                $top = ['kind' => __('listen.search.kind_artist'), 'title' => $artist->name, 'url' => route('artists.show', $artist), 'image' => $artist->avatar_url ?? $artist->cover_url, 'artist' => true, 'queue' => collect(), 'context' => null, 'byline' => null];
            }
        }
    @endphp

    <header @class(['page-head', 'page-head--search', 'page-head--tinted' => $query === ''])>
        <form class="search-box search-box--page" action="{{ route('listen.search') }}" method="get" role="search" data-live-search-form>
            <label class="sr-only" for="search-page-query">{{ __('listen.search_box.label') }}</label>
            {{ Icon::svg('search', 'search-box__icon') }}
            <input id="search-page-query" name="q" value="{{ $query }}" placeholder="{{ __('listen.search_box.placeholder') }}" autocomplete="off" spellcheck="false" enterkeyhint="search" data-live-search aria-controls="search-page-suggestions" aria-expanded="false" aria-autocomplete="list">
            <div class="search-suggestions" id="search-page-suggestions" data-search-suggestions role="region" aria-label="{{ __('listen.search_box.suggestions') }}" hidden>
                <p class="search-suggestions__status" data-search-status role="status"></p>
                <div data-search-list></div>
                <button class="search-suggestions__all" type="submit">{{ __('listen.search_box.all_results') }} {{ Icon::svg('arrow-up-right') }}</button>
            </div>
        </form>

        @if ($query === '')
            <h1 class="page-head__title">{{ __('listen.search.title') }}</h1>
            <p class="page-head__lede">{{ __('listen.search.intro') }} <span class="page-head__hint">{!! __('listen.search.shortcut', ['key' => '<kbd>/</kbd>']) !!}</span></p>
        @else
            <p class="eyebrow">{{ __('listen.search.results_for') }}</p>
            <h1 class="page-head__title page-head__title--query">“{{ $query }}”</h1>
            @if ($hasResults)
                <div class="chip-row" role="toolbar" aria-label="{{ __('listen.search.filters') }}" data-search-filters>
                    <button class="chip is-active" type="button" data-search-filter="all" aria-pressed="true">{{ __('listen.search.all') }}</button>
                    @if ($tracks->isNotEmpty())<button class="chip" type="button" data-search-filter="tracks" aria-pressed="false">{{ __('listen.search.songs') }} <span class="mono">{{ $tracks->count() }}</span></button>@endif
                    @if ($artists->isNotEmpty())<button class="chip" type="button" data-search-filter="artists" aria-pressed="false">{{ __('listen.search.artists') }} <span class="mono">{{ $artists->count() }}</span></button>@endif
                    @if ($albums->isNotEmpty())<button class="chip" type="button" data-search-filter="albums" aria-pressed="false">{{ __('listen.search.albums') }} <span class="mono">{{ $albums->count() }}</span></button>@endif
                </div>
            @endif
        @endif
    </header>

    @if ($query === '')
        <section class="listen-section" aria-labelledby="recent-searches" data-recent-searches hidden>
            <div class="section-heading">
                <h2 id="recent-searches">{{ __('listen.search.recent') }}</h2>
                <button class="text-button" type="button" data-recent-clear>{{ __('listen.search.clear') }}</button>
            </div>
            <div class="chip-row" data-recent-list></div>
        </section>

        @if ($genres->isNotEmpty())
            <section class="listen-section" aria-labelledby="browse-genres">
                <div class="section-heading"><h2 id="browse-genres">{{ __('listen.search.genres') }}</h2></div>
                <div class="genre-grid">
                    @foreach ($genres as $genre)
                        <a class="genre-tile" href="{{ route('listen.search', ['q' => $genre->genre]) }}" data-tone="{{ crc32(Str::lower($genre->genre)) % 6 }}">
                            <span class="genre-tile__name">{{ $genre->genre }}</span>
                            <span class="genre-tile__count mono">{{ trans_choice('listen.song_count', $genre->tracks_count) }}</span>
                            {{ Icon::svg('signal', 'genre-tile__glyph') }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @elseif (! $hasResults)
        <div class="listen-section">
            @include('listen.components.empty-state', ['title' => __('listen.search.no_results_title', ['query' => $query]), 'message' => __('listen.search.no_results_body'), 'icon' => 'search', 'action' => __('listen.nav.browse'), 'actionUrl' => route('listen.catalogue')])
        </div>
    @else
        <div class="search-results" data-search-results data-filter="all">
            <div class="search-top" data-search-group="all">
                <section class="search-top__best" aria-labelledby="top-result">
                    <h2 class="search-top__heading" id="top-result">{{ __('listen.search.top_result') }}</h2>
                    <article class="top-result">
                        @include('listen.components.art', ['image' => $top['image'], 'seed' => $top['title'], 'glyph' => $top['title'], 'icon' => null, 'class' => 'top-result__art'.($top['artist'] ? ' art--artist' : '')])
                        <a class="top-result__title" href="{{ $top['url'] }}">{{ $top['title'] }}</a>
                        <p class="top-result__meta"><span class="tag">{{ $top['kind'] }}</span>@if ($top['byline']) <span>{{ $top['byline'] }}</span>@endif</p>
                        @if ($top['queue']->isNotEmpty())
                            <button class="play-fab" type="button" data-play-track data-track='{{ PlayerTrack::json($top['queue']->first()) }}' data-track-queue='{{ PlayerTrack::json($top['queue']) }}' data-play-context="{{ $top['context'] }}" data-play-context-label="{{ $top['title'] }}" aria-label="{{ __('listen.play_label', ['label' => $top['title']]) }}">
                                {{ Icon::svg('play', 'play-fab__play') }}{{ Icon::svg('pause', 'play-fab__pause') }}
                            </button>
                        @endif
                    </article>
                </section>
                @if ($tracks->isNotEmpty())
                    <section class="search-top__songs" aria-labelledby="top-songs">
                        <h2 class="search-top__heading" id="top-songs">{{ __('listen.search.songs') }}</h2>
                        @include('listen.components.track-list', ['tracks' => $tracks->take(4), 'queue' => $trackQueue, 'context' => 'search:'.$query, 'contextLabel' => __('listen.search.context', ['query' => $query]), 'numbered' => false, 'header' => false, 'showAlbum' => false])
                    </section>
                @endif
            </div>

            @if ($tracks->isNotEmpty())
                <section class="listen-section" data-search-group="tracks" aria-labelledby="all-songs">
                    <div class="section-heading"><h2 id="all-songs">{{ __('listen.search.songs') }}</h2><span class="section-heading__note">{{ trans_choice('listen.search.result_count', $tracks->count()) }}</span></div>
                    @include('listen.components.track-list', ['tracks' => $tracks, 'queue' => $trackQueue, 'context' => 'search:'.$query, 'contextLabel' => __('listen.search.context', ['query' => $query])])
                </section>
            @endif

            @if ($artists->isNotEmpty())
                <section class="listen-section" data-search-group="all artists" aria-labelledby="artist-results">
                    <div class="section-heading"><h2 id="artist-results">{{ __('listen.search.artists') }}</h2></div>
                    <div class="media-grid">
                        @foreach ($artists as $artist)
                            @include('listen.components.media-card', ['item' => $artist, 'kind' => 'artist'])
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($albums->isNotEmpty())
                <section class="listen-section" data-search-group="all albums" aria-labelledby="album-results">
                    <div class="section-heading"><h2 id="album-results">{{ __('listen.search.albums') }}</h2></div>
                    <div class="media-grid">
                        @foreach ($albums as $album)
                            @include('listen.components.media-card', ['item' => $album])
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif
@endsection
