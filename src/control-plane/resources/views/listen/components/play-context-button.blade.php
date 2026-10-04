@use('App\Support\Icon')
@use('App\Support\PlayerTrack')
@php
    $size ??= null;
@endphp
<button @class(['play-button', 'play-button--'.$size => $size]) type="button" data-play-track data-track='{{ PlayerTrack::json($queue->first()) }}' data-track-queue='{{ PlayerTrack::json($queue) }}' data-play-context="{{ $context }}" data-play-context-label="{{ $label }}" aria-label="{{ __('listen.play_label', ['label' => $label]) }}">
    {{ Icon::svg('play', 'play-button__play') }}{{ Icon::svg('pause', 'play-button__pause') }}
</button>
