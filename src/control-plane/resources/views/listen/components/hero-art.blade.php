@php
    $class ??= '';
    $creditClass ??= '';
    $sizes ??= '100vw';
    $eager ??= false;
    // The hero in use. 'bang-dia' is the project's own drawing (scripts/generate-record-shop-hero.py) and needs no credit.
    // 'hu-tieu-go' is An Nguyen's artwork (scripts/generate-hero-images.py): show it only with the artist's written
    // permission, and always with its credit line.
    $heroes = [
        'bang-dia' => ['widths' => [960, 1440, 1920, 2560], 'credit' => null],
        'hu-tieu-go' => ['widths' => [960, 1440, 1920], 'credit' => ['name' => 'An Nguyen', 'url' => 'https://www.artstation.com/annguyen1996']],
    ];
    $name = 'bang-dia';
    $hero = $heroes[$name];
    $srcset = collect($hero['widths'])->map(fn (int $width) => asset("images/hero-{$name}-{$width}.webp")." {$width}w")->join(', ');
@endphp
<img class="{{ $class }}"
     src="{{ asset("images/hero-{$name}-1440.webp") }}"
     srcset="{{ $srcset }}"
     sizes="{{ $sizes }}" width="1920" height="1080" alt="" decoding="async" @if ($eager) fetchpriority="high" @else loading="lazy" @endif>
@if ($hero['credit'])
    <a class="hero-credit {{ $creditClass }}" href="{{ $hero['credit']['url'] }}" target="_blank" rel="noopener">{{ __('listen.hero_credit', ['name' => $hero['credit']['name']]) }}</a>
@endif
