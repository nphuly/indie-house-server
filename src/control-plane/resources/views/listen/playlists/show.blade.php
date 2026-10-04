@extends('listen.layouts.app', ['title' => $playlist->title])

@use('App\Support\Duration')
@use('App\Support\Icon')
@use('App\Support\PlayerTrack')

@section('content')
    @php
        $playlistQueue = PlayerTrack::queue($tracks);
        $covers = $playlistQueue->pluck('artwork')->filter()->unique()->values()->all();
        $context = 'playlist:'.$playlist->public_id;
        $isOwner = auth()->user()?->id === $playlist->user_id;
    @endphp

    @component('listen.components.entity-hero', [
        'eyebrow' => $playlist->is_public ? __('listen.playlist.public') : __('listen.playlist.private'),
        'title' => $playlist->title,
        'images' => count($covers) >= 4 ? $covers : array_slice($covers, 0, 1),
        'seed' => $playlist->public_id,
        'description' => $playlist->description,
    ])
        @slot('meta')
            <span class="entity-hero__byline">
                <span class="avatar avatar--xs" aria-hidden="true">{{ mb_strtoupper(mb_substr($playlist->user->name, 0, 1)) }}</span>
                {{ $playlist->user->name }}
            </span>
            <span class="dot" aria-hidden="true"></span>
            <span class="mono">{{ $tracks->isNotEmpty() ? __('listen.count_and_duration', ['count' => trans_choice('listen.track_count', $tracks->count()), 'duration' => Duration::long($tracks->sum('duration_seconds'))]) : trans_choice('listen.track_count', 0) }}</span>
        @endslot
        @if ($tracks->isNotEmpty())
            @slot('play')
                @include('listen.components.play-context-button', ['queue' => $playlistQueue, 'context' => $context, 'label' => $playlist->title])
            @endslot
            <button class="icon-button icon-button--lg" type="button" data-play-shuffle data-track-queue='{{ PlayerTrack::json($playlistQueue) }}' data-play-context="{{ $context }}" data-play-context-label="{{ $playlist->title }}" aria-label="{{ __('listen.shuffle_label', ['label' => $playlist->title]) }}" title="{{ __('listen.shuffle') }}">{{ Icon::svg('shuffle') }}</button>
        @endif

        @can('update', $playlist)
            <details class="edit-popover" data-popover>
                <summary class="icon-button icon-button--lg" aria-label="{{ __('listen.playlist.edit') }}" title="{{ __('listen.playlist.edit') }}">{{ Icon::svg('pencil') }}</summary>
                <div class="menu-panel edit-popover__panel">
                    <form class="form-stack" action="{{ route('playlists.update', $playlist) }}" method="post">
                        @csrf
                        @method('PATCH')
                        <p class="menu-label">{{ __('listen.playlist.edit') }}</p>
                        <div class="field">
                            <label for="edit-playlist-title">{{ __('listen.library.name') }}</label>
                            <input id="edit-playlist-title" name="title" value="{{ old('title', $playlist->title) }}" required minlength="2" maxlength="120">
                            @error('title') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="edit-playlist-description">{{ __('listen.library.description') }} <span>{{ __('listen.optional') }}</span></label>
                            <textarea id="edit-playlist-description" name="description" rows="3" maxlength="1000">{{ old('description', $playlist->description) }}</textarea>
                        </div>
                        <input type="hidden" name="is_public" value="0">
                        <label class="switch-row" for="edit-playlist-public">
                            <span><strong>{{ __('listen.library.public') }}</strong><small>{{ __('listen.library.public_hint') }}</small></span>
                            <input class="switch" id="edit-playlist-public" name="is_public" value="1" type="checkbox" role="switch" @checked(old('is_public', $playlist->is_public))>
                        </label>
                        <button class="button button--primary" type="submit">{{ __('listen.playlist.save') }}</button>
                    </form>
                    <hr class="menu-divider">
                    <form action="{{ route('playlists.destroy', $playlist) }}" method="post" data-confirm="{{ __('listen.playlist.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <button class="menu-item menu-item--danger" type="submit">{{ Icon::svg('trash') }}<span data-confirm-label>{{ __('listen.playlist.delete') }}</span></button>
                    </form>
                </div>
            </details>
        @endcan
        @if ($playlist->is_public)
            <button class="icon-button icon-button--lg" type="button" data-copy-link aria-label="{{ __('listen.playlist.copy_link') }}" title="{{ __('listen.copy_link') }}">{{ Icon::svg('share') }}</button>
        @endif
    @endcomponent

    <section class="listen-section listen-section--flush" aria-label="{{ __('listen.songs_heading') }}">
        @if ($tracks->isNotEmpty())
            @include('listen.components.track-list', ['tracks' => $tracks, 'queue' => $playlistQueue, 'context' => $context, 'contextLabel' => $playlist->title, 'editablePlaylist' => $isOwner ? $playlist : null])
        @else
            @include('listen.components.empty-state', [
                'title' => __('listen.playlist.empty_title'),
                'message' => $isOwner ? __('listen.playlist.empty_owner') : __('listen.playlist.empty_other'),
                'action' => $isOwner ? __('listen.nav.browse') : null,
                'actionUrl' => route('listen.catalogue'),
                'icon' => 'note',
            ])
        @endif
    </section>
@endsection
