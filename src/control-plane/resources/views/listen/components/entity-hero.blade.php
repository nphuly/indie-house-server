@php
    $images = array_values(array_filter($images ?? []));
    $artist ??= false;
    $description ??= null;
    $alt ??= '';
    $seed ??= $title;
    $titleLength = mb_strlen($title);
@endphp

{{-- Compact header that fades in once the hero scrolls away (see initialiseStickyHead in app.js). --}}
<div class="sticky-head" data-sticky-head inert>
    {{ $play ?? '' }}
    <strong class="sticky-head__title">{{ $title }}</strong>
</div>

<header @class(['entity-hero', 'entity-hero--artist' => $artist]) data-entity-hero>
    <div class="entity-hero__backdrop" aria-hidden="true">
        @if ($images)
            <img src="{{ $images[0] }}" alt="">
        @else
            <span class="art" data-tone="{{ crc32((string) $seed) % 6 }}"></span>
        @endif
    </div>
    <div class="entity-hero__inner">
        @if (count($images) >= 4)
            <span class="art art-mosaic entity-hero__art">
                @foreach (array_slice($images, 0, 4) as $image)
                    <img src="{{ $image }}" alt="" decoding="async">
                @endforeach
            </span>
        @else
            @include('listen.components.art', ['image' => $images[0] ?? null, 'alt' => $alt, 'seed' => $seed, 'glyph' => $title, 'icon' => null, 'class' => 'entity-hero__art'.($artist ? ' art--artist' : ''), 'eager' => true])
        @endif
        <div class="entity-hero__copy">
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h1 @class(['entity-hero__title', 'is-long' => $titleLength > 18, 'is-xlong' => $titleLength > 34])>{{ $title }}</h1>
            @if ($description)
                <p class="entity-hero__description">{{ $description }}</p>
            @endif
            <p class="entity-hero__meta">{{ $meta }}</p>
        </div>
    </div>
</header>

<div class="action-bar">
    {{ $play ?? '' }}
    {{ $slot }}
</div>
