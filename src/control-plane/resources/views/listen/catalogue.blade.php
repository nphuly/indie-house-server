@extends('listen.layouts.app', ['title' => __('listen.browse.title')])

@use('App\Support\PlayerTrack')

@section('content')
    <header class="page-head page-head--tinted">
        <h1 class="page-head__title">{{ __('listen.browse.title') }}</h1>
        <p class="page-head__lede">{{ __('listen.browse.summary', ['tracks' => trans_choice('listen.song_count', $tracks->total()), 'artists' => trans_choice('listen.artist_count', $artists->count())]) }}</p>
    </header>

    @if ($albums->isNotEmpty())
        <section class="listen-section" aria-labelledby="catalogue-albums">
            <div class="section-heading"><h2 id="catalogue-albums">{{ __('listen.browse.new_albums') }}</h2></div>
            <div class="shelf" data-shelf>
                @foreach ($albums as $album)
                    @include('listen.components.media-card', ['item' => $album])
                @endforeach
            </div>
        </section>
    @endif

    <section class="listen-section" aria-labelledby="catalogue-artists">
        <div class="section-heading"><h2 id="catalogue-artists">{{ __('listen.browse.artists') }}</h2></div>
        @if ($artists->isNotEmpty())
            <div class="media-grid">
                @foreach ($artists as $artist)
                    @include('listen.components.media-card', ['item' => $artist, 'kind' => 'artist'])
                @endforeach
            </div>
        @else
            @include('listen.components.empty-state', ['title' => __('listen.browse.artists_empty_title'), 'message' => __('listen.browse.artists_empty_body'), 'icon' => 'mic'])
        @endif
    </section>

    <section class="listen-section" aria-labelledby="catalogue-tracks">
        <div class="section-heading">
            <h2 id="catalogue-tracks">{{ __('listen.browse.all_songs') }}</h2>
            <span class="section-heading__note">{{ __('listen.browse.by_plays') }}</span>
        </div>
        @if ($tracks->isNotEmpty())
            @include('listen.components.track-list', ['tracks' => $tracks, 'start' => $tracks->firstItem(), 'queue' => PlayerTrack::queue($tracks->getCollection()), 'context' => 'catalogue:'.$tracks->currentPage(), 'contextLabel' => __('listen.browse.title')])
            @include('listen.components.pagination', ['paginator' => $tracks])
        @else
            @include('listen.components.empty-state', ['title' => __('listen.browse.songs_empty_title'), 'message' => __('listen.browse.songs_empty_body')])
        @endif
    </section>
@endsection
