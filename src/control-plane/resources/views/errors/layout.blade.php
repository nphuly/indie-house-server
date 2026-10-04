@php
    $title = __("errors.{$code}.title");
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07090E">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · Indie House</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/indie-house-mark.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="error-body">
    <main class="error-shell">
        <a class="brand" href="{{ url('/') }}">
            <img class="brand__mark" src="{{ asset('images/indie-house-mark.svg') }}" alt="" aria-hidden="true">
            <span class="brand__word">indie house</span>
        </a>
        <p class="error-code" aria-hidden="true" data-code="{{ $code }}">{{ $code }}</p>
        <p class="eyebrow">{{ __('errors.code', ['code' => $code]) }}</p>
        <h1 class="error-title">{{ $title }}</h1>
        <p class="error-message">{{ __("errors.{$code}.message") }}</p>
        <div class="error-actions">
            <a class="button button--primary" href="{{ url('/') }}">{{ __('errors.home') }}</a>
            @if ($code !== 503)
                <a class="button button--outline" href="{{ url('/search') }}">{{ __('errors.search') }}</a>
            @endif
        </div>
    </main>
</body>
</html>
