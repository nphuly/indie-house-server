@use('App\Support\Icon')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07090E">
    <title>{{ $title }} · Indie House</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/indie-house-mark.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-visual">
            @include('listen.components.hero-art', ['class' => 'auth-visual__photo', 'sizes' => '(max-width: 1023px) 100vw, 55vw', 'eager' => true])
        </section>

        <section class="auth-panel">
            <div class="auth-panel__top">
                <a class="brand auth-panel__brand" href="{{ route('listen.home') }}">
                    <img class="brand__mark" src="{{ asset('images/indie-house-mark.svg') }}" alt="" aria-hidden="true">
                    <span class="brand__word">indie house</span>
                </a>
                @include('listen.components.locale-switch')
            </div>
            <div class="auth-card">
                @yield('content')
            </div>
            <a class="auth-back" href="{{ route('listen.home') }}">{{ Icon::svg('back') }}{{ __('auth.back_home') }}</a>
        </section>
    </main>
</body>
</html>
