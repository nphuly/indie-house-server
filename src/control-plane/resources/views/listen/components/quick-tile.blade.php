@use('App\Support\Icon')
@use('App\Support\PlayerTrack')
@php
    $image ??= null;
    $icon ??= null;
    $seed ??= $label;
    $queue ??= null;
    $context ??= null;
@endphp

<article class="quick-tile">
    @include('listen.components.art', ['image' => $image, 'seed' => $seed, 'glyph' => $label, 'icon' => $icon, 'class' => 'quick-tile__art'])
    <a class="quick-tile__title" href="{{ $url }}">{{ $label }}</a>
    @if ($queue && $queue->isNotEmpty())
        <button class="play-fab play-fab--sm" type="button" data-play-track data-track='{{ PlayerTrack::json($queue->first()) }}' data-track-queue='{{ PlayerTrack::json($queue) }}' @if ($context) data-play-context="{{ $context }}" data-play-context-label="{{ $label }}" @endif aria-label="{{ __('listen.play_label', ['label' => $label]) }}">
            {{ Icon::svg('play', 'play-fab__play') }}{{ Icon::svg('pause', 'play-fab__pause') }}
        </button>
    @endif
</article>
