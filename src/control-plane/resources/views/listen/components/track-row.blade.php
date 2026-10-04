@use('App\Support\Icon')
@use('App\Support\PlayerTrack')
@php
    $index ??= null;
    $showAlbum ??= true;
    $showArt ??= true;
    $editablePlaylist ??= null;
    $payload = PlayerTrack::make($track, $contextAlbum ?? null);
@endphp

<div class="track-row" data-track-row data-track-id="{{ $track->public_id }}" data-play-track data-track='{{ PlayerTrack::json($payload) }}'>
    <div class="track-row__index">
        <span class="track-row__number">{{ $index ?? '' }}</span>
        <span class="eq track-row__eq" aria-hidden="true"><i></i><i></i><i></i></span>
        <button class="track-row__play" type="button" data-play-track aria-label="{{ __('listen.track.play', ['title' => $track->title, 'artist' => $payload['artist']]) }}">{{ Icon::svg('play', 'track-row__play-icon') }}{{ Icon::svg('pause', 'track-row__pause-icon') }}</button>
    </div>
    <div class="track-row__main">
        @if ($showArt)
            @include('listen.components.art', ['image' => $payload['artwork'], 'seed' => $payload['album'] ?? $track->title, 'glyph' => $track->title, 'class' => 'track-row__art'])
        @endif
        <div class="track-row__text">
            <span class="track-row__title">{{ $track->title }}</span>
            <a class="track-row__artist" href="{{ $payload['artistUrl'] }}">{{ $payload['artist'] }}</a>
        </div>
    </div>
    @if ($showAlbum)
        <div class="track-row__album">
            @if ($payload['albumUrl'])
                <a href="{{ $payload['albumUrl'] }}">{{ $payload['album'] }}</a>
            @else
                <span>{{ __('listen.single') }}</span>
            @endif
        </div>
    @endif
    <div class="track-row__end">
        <span class="track-row__duration">{{ $track->formatted_duration }}</span>
        @include('listen.components.track-menu', ['track' => $track, 'payload' => $payload, 'editablePlaylist' => $editablePlaylist])
    </div>
</div>
