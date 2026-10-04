@use('App\Support\Icon')
@use('App\Support\PlayerTrack')
@php
    $showAlbum ??= true;
    $showArt ??= true;
    $numbered ??= true;
    $start ??= 1;
    $header ??= true;
    $queue ??= null;
    $context ??= null;
    $contextLabel ??= null;
    $contextAlbum ??= null;
    $editablePlaylist ??= null;
@endphp

<div @class(['track-list', 'track-list--no-album' => ! $showAlbum, 'track-list--plain' => ! $numbered && $showArt])
    @if ($queue) data-track-queue='{{ PlayerTrack::json($queue) }}' @endif
    @if ($context) data-play-context="{{ $context }}" @endif
    @if ($contextLabel) data-play-context-label="{{ $contextLabel }}" @endif>
    @if ($header)
        <div class="track-list__head" aria-hidden="true">
            <span>#</span>
            <span>{{ __('listen.track.title') }}</span>
            @if ($showAlbum)<span>{{ __('listen.album_label') }}</span>@endif
            <span class="track-list__head-end">{{ Icon::svg('clock') }}</span>
        </div>
    @endif
    @foreach ($tracks as $track)
        @include('listen.components.track-row', [
            'track' => $track,
            'index' => $numbered ? $start + $loop->index : null,
            'showAlbum' => $showAlbum,
            'showArt' => $showArt,
            'contextAlbum' => $contextAlbum,
            'editablePlaylist' => $editablePlaylist,
        ])
    @endforeach
</div>
