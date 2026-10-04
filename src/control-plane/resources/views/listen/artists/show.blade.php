@extends('listen.layouts.app', ['title' => $artist->name])

@use('App\Support\Icon')
@use('App\Support\PlayerTrack')

@section('content')
    @php
        $popular = $tracks->sortByDesc('play_count')->values();
        $popularQueue = PlayerTrack::queue($popular);
        $listens = \App\Support\Duration::plays((int) $tracks->sum('play_count'));
        $context = 'artist:'.$artist->public_id;
        $portrait = $artist->avatar_url ?? $artist->cover_url;
    @endphp

    @component('listen.components.entity-hero', [
        'eyebrow' => __('listen.artist_label'),
        'title' => $artist->name,
        'images' => [$portrait],
        'alt' => __('listen.artist.photo', ['name' => $artist->name]),
        'artist' => true,
    ])
        @slot('meta')
            <span class="mono">{{ __('listen.artist.meta', ['plays' => $listens, 'tracks' => trans_choice('listen.track_count', $tracks->count())]) }}</span>
        @endslot
        @if ($popular->isNotEmpty())
            @slot('play')
                @include('listen.components.play-context-button', ['queue' => $popularQueue, 'context' => $context, 'label' => $artist->name])
            @endslot
            <button class="icon-button icon-button--lg" type="button" data-play-shuffle data-track-queue='{{ PlayerTrack::json($popularQueue) }}' data-play-context="{{ $context }}" data-play-context-label="{{ $artist->name }}" aria-label="{{ __('listen.shuffle_label', ['label' => $artist->name]) }}" title="{{ __('listen.shuffle') }}">{{ Icon::svg('shuffle') }}</button>
        @endif
        <button class="icon-button icon-button--lg" type="button" data-copy-link aria-label="{{ __('listen.artist.copy_link') }}" title="{{ __('listen.copy_link') }}">{{ Icon::svg('share') }}</button>
    @endcomponent

    <section class="listen-section listen-section--flush" aria-labelledby="popular-heading">
        <div class="section-heading"><h2 id="popular-heading">{{ __('listen.artist.popular') }}</h2></div>
        @if ($popular->isNotEmpty())
            <div class="expandable" data-expandable>
                @include('listen.components.track-list', ['tracks' => $popular, 'queue' => $popularQueue, 'context' => $context, 'contextLabel' => $artist->name, 'header' => false])
                @if ($popular->count() > 5)
                    <button class="text-button expandable__toggle" type="button" data-expand-toggle aria-expanded="false" data-label-more="{{ __('listen.artist.show_more') }}" data-label-less="{{ __('listen.artist.show_less') }}">{{ __('listen.artist.show_more') }}</button>
                @endif
            </div>
        @else
            @include('listen.components.empty-state', ['title' => __('listen.artist.empty_title'), 'message' => __('listen.artist.empty_body')])
        @endif
    </section>

    @if ($albums->isNotEmpty())
        <section class="listen-section" aria-labelledby="releases-heading">
            <div class="section-heading"><h2 id="releases-heading">{{ __('listen.artist.releases') }}</h2></div>
            <div class="media-grid">
                @foreach ($albums as $album)
                    @include('listen.components.media-card', ['item' => $album, 'meta' => $album->tracks->count() === 1 ? __('listen.single') : trans_choice('listen.track_count', $album->tracks->count())])
                @endforeach
            </div>
        </section>
    @endif

    @if ($artist->bio)
        <section class="listen-section" aria-labelledby="about-heading">
            <div class="section-heading"><h2 id="about-heading">{{ __('listen.artist.about') }}</h2></div>
            <article class="about-card">
                <div class="about-card__media" aria-hidden="true">
                    @include('listen.components.art', ['image' => $portrait, 'seed' => $artist->name, 'glyph' => $artist->name, 'icon' => null, 'class' => 'about-card__art'])
                </div>
                <div class="about-card__copy">
                    <p class="about-card__stat mono">{{ $listens }}</p>
                    <p class="about-card__bio">{{ $artist->bio }}</p>
                </div>
            </article>
        </section>
    @endif
@endsection
