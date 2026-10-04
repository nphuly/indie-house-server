@use('App\Support\Icon')
@php
    $image ??= null;
    $alt ??= '';
    $seed ??= 'indie-house';
    $glyph ??= null;
    $icon ??= null;
    $class ??= '';
    $eager ??= false;
@endphp
{{-- Artwork frame. Without an image it renders a tinted placeholder whose tone is derived from $seed, so the same item keeps the same colour everywhere. --}}
<span @class(['art', $class]) data-tone="{{ crc32((string) $seed) % 6 }}">
    @if ($image)
        <img src="{{ $image }}" alt="{{ $alt }}" @unless ($eager) loading="lazy" @endunless decoding="async">
    @elseif ($icon)
        {{ Icon::svg($icon, 'art__icon') }}
    @elseif ($glyph)
        <span class="art__glyph" aria-hidden="true">{{ mb_strtoupper(mb_substr($glyph, 0, 1)) }}</span>
    @endif
</span>
