@extends('legacy.layout')

@section('title', __('legacy.title'))

@section('content')
    <form class="logout" method="post" action="{{ route('legacy.logout') }}">
        @csrf
        <button type="submit">{{ __('legacy.sign_out') }}</button>
    </form>
    <p class="eyebrow">{{ __('legacy.library_label') }}</p>
    <h1>{{ __('legacy.title') }}</h1>
    <p class="intro">{{ __('legacy.library_description') }}</p>

    <form class="search" method="get" action="{{ route('legacy.index') }}">
        <label for="q">{{ __('legacy.search') }}</label>
        <div class="search-row">
            <input id="q" name="q" type="search" value="{{ $query }}" maxlength="120">
            <button type="submit">{{ __('legacy.go') }}</button>
        </div>
    </form>

    <p class="section-label">{{ $query === '' ? __('legacy.all_tracks') : __('legacy.search_results') }}</p>
    @forelse ($tracks as $track)
        <article class="track">
            <div class="track-art"><img src="{{ asset('images/indie-house-mark.png') }}" alt="" aria-hidden="true"></div>
            <div class="track-info">
                <h2>{{ $track->title }}</h2>
                <p>{{ $track->artist?->name }}</p>
            </div>
            <div class="track-actions">
                <a class="button" href="{{ route('legacy.audio', $track) }}?title={{ rawurlencode($track->title) }}&amp;artist={{ rawurlencode($track->artist?->name ?? '') }}">{{ __('legacy.play_in_app') }}</a>
                <div class="browser-player">
                    <span>{{ __('legacy.browser_player') }}</span>
                    <audio controls preload="none" src="{{ route('legacy.audio', $track) }}">
                        <a href="{{ route('legacy.audio', $track) }}">MP3</a>
                    </audio>
                </div>
            </div>
        </article>
    @empty
        <p class="empty">{{ __('legacy.empty') }}</p>
    @endforelse

    @if ($tracks->hasPages())
        <nav class="pagination" aria-label="Pagination">
            @if ($tracks->previousPageUrl())
                <a href="{{ $tracks->previousPageUrl() }}">{{ __('legacy.previous') }}</a>
            @endif
            <span>{{ __('legacy.page', ['current' => $tracks->currentPage(), 'last' => $tracks->lastPage()]) }}</span>
            @if ($tracks->nextPageUrl())
                <a href="{{ $tracks->nextPageUrl() }}">{{ __('legacy.next') }}</a>
            @endif
        </nav>
    @endif
@endsection
