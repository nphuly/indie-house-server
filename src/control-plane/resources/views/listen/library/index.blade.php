@extends('listen.layouts.app', ['title' => __('listen.library.title')])

@use('App\Support\Icon')
@use('App\Support\PlayerTrack')

@section('content')
    @php
        $historyTracks = $history->pluck('track');
        $historyQueue = PlayerTrack::queue($historyTracks);
        $createOpen = $errors->any() || old('title') !== null;
    @endphp

    <header class="page-head page-head--tinted">
        <h1 class="page-head__title">{{ __('listen.library.title') }}</h1>
    </header>

    <section class="listen-section" id="playlists" aria-labelledby="playlists-heading">
        <div class="section-heading">
            <h2 id="playlists-heading">{{ __('listen.library.playlists') }}</h2>
        </div>

        <div class="media-grid media-grid--library">
            <details class="create-card" id="create-playlist" data-create-playlist @if ($createOpen) open @endif>
                <summary class="create-card__summary">
                    <span class="create-card__icon" aria-hidden="true">{{ Icon::svg('plus') }}</span>
                    <span class="create-card__label">{{ __('listen.library.new_playlist') }}</span>
                    <span class="create-card__hint">{{ __('listen.library.new_playlist_hint') }}</span>
                </summary>
                <form class="form-stack create-card__form" action="{{ route('playlists.store') }}" method="post">
                    @csrf
                    <div class="field">
                        <label for="playlist-title">{{ __('listen.library.name') }}</label>
                        <input id="playlist-title" name="title" required minlength="2" maxlength="120" value="{{ old('title') }}" placeholder="{{ __('listen.library.name_placeholder') }}" @error('title') aria-invalid="true" aria-describedby="playlist-title-error" @enderror>
                        @error('title') <p class="field-error" id="playlist-title-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label for="playlist-description">{{ __('listen.library.description') }} <span>{{ __('listen.optional') }}</span></label>
                        <textarea id="playlist-description" name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                        @error('description') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                    </div>
                    <input type="hidden" name="is_public" value="0">
                    <label class="switch-row" for="playlist-public">
                        <span><strong>{{ __('listen.library.public') }}</strong><small>{{ __('listen.library.public_hint') }}</small></span>
                        <input class="switch" id="playlist-public" name="is_public" value="1" type="checkbox" role="switch" @checked(old('is_public', true))>
                    </label>
                    <div class="form-actions">
                        <button class="button button--primary" type="submit">{{ __('listen.library.create') }}</button>
                        <button class="button button--ghost" type="button" data-close-details>{{ __('listen.cancel') }}</button>
                    </div>
                </form>
            </details>

            @foreach ($playlists as $playlist)
                @php
                    $covers = $playlist->tracks->map->artwork_url->filter()->unique()->values();
                @endphp
                <article class="media-card media-card--playlist">
                    <div class="media-card__art-wrap">
                        @if ($covers->count() >= 4)
                            <span class="art art-mosaic media-card__art">
                                @foreach ($covers->take(4) as $cover)<img src="{{ $cover }}" alt="" loading="lazy" decoding="async">@endforeach
                            </span>
                        @else
                            @include('listen.components.art', ['image' => $covers->first(), 'seed' => $playlist->public_id, 'glyph' => $playlist->title, 'icon' => null, 'class' => 'media-card__art'])
                        @endif
                    </div>
                    <a class="media-card__title" href="{{ route('playlists.show', $playlist) }}">{{ $playlist->title }}</a>
                    <p class="media-card__meta">
                        @unless ($playlist->is_public){{ Icon::svg('lock', 'media-card__lock') }}@endunless
                        {{ __('listen.library.playlist_meta', ['count' => trans_choice('listen.track_count', $playlist->tracks_count), 'visibility' => $playlist->is_public ? __('listen.library.public') : __('listen.library.private')]) }}
                    </p>
                </article>
            @endforeach
        </div>
        @if ($playlists->isEmpty())
            <p class="library-hint">{{ __('listen.library.no_playlists') }}</p>
        @endif
    </section>

    <section class="listen-section" id="history" aria-labelledby="history-heading">
        <div class="section-heading">
            <h2 id="history-heading">{{ __('listen.library.history') }}</h2>
        </div>
        @if ($history->isNotEmpty())
            @include('listen.components.track-list', ['tracks' => $historyTracks, 'queue' => $historyQueue, 'context' => 'history', 'contextLabel' => __('listen.library.history')])
        @else
            @include('listen.components.empty-state', ['title' => __('listen.library.history_empty_title'), 'message' => __('listen.library.history_empty_body'), 'action' => __('listen.nav.browse'), 'actionUrl' => route('listen.catalogue'), 'icon' => 'history'])
        @endif
    </section>
@endsection
