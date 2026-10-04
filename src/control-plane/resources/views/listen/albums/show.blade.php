@extends('listen.layouts.app', ['title' => $album->title])

@use('App\Support\Icon')
@use('App\Support\PlayerTrack')

@section('content')
    @php
        $albumQueue = PlayerTrack::queue($tracks, $album);
        $totalSeconds = $tracks->sum('duration_seconds');
        $context = 'album:'.$album->public_id;
    @endphp

    @component('listen.components.entity-hero', [
        'eyebrow' => $tracks->count() === 1 ? __('listen.single') : __('listen.album_label'),
        'title' => $album->title,
        'images' => [$album->cover_url],
        'alt' => __('listen.album.cover', ['title' => $album->title]),
        'description' => $album->description,
    ])
        @slot('meta')
            <a class="entity-hero__byline" href="{{ route('artists.show', $album->artist) }}">
                @include('listen.components.art', ['image' => $album->artist->avatar_url, 'seed' => $album->artist->name, 'glyph' => $album->artist->name, 'icon' => null, 'class' => 'entity-hero__byline-art art--artist'])
                {{ $album->artist->name }}
            </a>
            <span class="dot" aria-hidden="true"></span>
            <span class="mono">{{ __('listen.count_and_duration', ['count' => trans_choice('listen.track_count', $tracks->count()), 'duration' => \App\Support\Duration::long($totalSeconds)]) }}</span>
        @endslot
        @slot('play')
            @include('listen.components.play-context-button', ['queue' => $albumQueue, 'context' => $context, 'label' => $album->title])
        @endslot

        <button class="icon-button icon-button--lg" type="button" data-play-shuffle data-track-queue='{{ PlayerTrack::json($albumQueue) }}' data-play-context="{{ $context }}" data-play-context-label="{{ $album->title }}" aria-label="{{ __('listen.shuffle_label', ['label' => $album->title]) }}" title="{{ __('listen.shuffle') }}">{{ Icon::svg('shuffle') }}</button>
        <button class="icon-button icon-button--lg" type="button" data-copy-link aria-label="{{ __('listen.album.copy_link') }}" title="{{ __('listen.copy_link') }}">{{ Icon::svg('share') }}</button>
    @endcomponent

    <section class="listen-section listen-section--flush" aria-label="{{ __('listen.songs_heading') }}">
        @include('listen.components.track-list', ['tracks' => $tracks, 'showAlbum' => false, 'showArt' => false, 'contextAlbum' => $album, 'queue' => $albumQueue, 'context' => $context, 'contextLabel' => $album->title])
        <p class="release-note">© {{ $album->artist->name }}</p>
    </section>
@endsection
