@use('App\Support\Icon')
@use('App\Support\PlayerTrack')
@php
    $kind ??= 'album';
    $isAlbum = $kind === 'album';
    $image = $isAlbum ? $item->cover_url : ($item->avatar_url ?? $item->cover_url);
    $label = $isAlbum ? $item->title : $item->name;
    $destination = $isAlbum ? route('albums.show', $item) : route('artists.show', $item);
    $queue = $isAlbum && $item->relationLoaded('tracks') && $item->tracks->isNotEmpty()
        ? PlayerTrack::queue($item->tracks, $item)
        : null;
    $cardMeta = $meta ?? ($isAlbum ? $item->artist->name : __('listen.artist_label'));
@endphp

<article @class(['media-card', 'media-card--artist' => ! $isAlbum])>
    <div class="media-card__art-wrap">
        @include('listen.components.art', ['image' => $image, 'seed' => $label, 'glyph' => $label, 'class' => 'media-card__art'.($isAlbum ? '' : ' art--artist')])
        @if ($queue)
            <button class="play-fab" type="button" data-play-track data-track='{{ PlayerTrack::json($queue->first()) }}' data-track-queue='{{ PlayerTrack::json($queue) }}' data-play-context="album:{{ $item->public_id }}" data-play-context-label="{{ $label }}" aria-label="{{ __('listen.album.play', ['title' => $label]) }}">
                {{ Icon::svg('play', 'play-fab__play') }}{{ Icon::svg('pause', 'play-fab__pause') }}
            </button>
        @endif
    </div>
    <a class="media-card__title" href="{{ $destination }}">{{ $label }}</a>
    <p class="media-card__meta">{{ $cardMeta }}</p>
</article>
