@use('App\Support\Icon')
@php
    $user = auth()->user();
    $libraryUrl = $user ? route('library.index') : route('login', ['intended' => route('library.index')]);
    $avatarUrl = $user?->avatarUrl();
    $canAccessAdmin = $user && $user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#07090E">
    <meta name="description" content="{{ __('listen.meta_description') }}">
    <title>{{ ($title ?? null) ? $title.' · ' : '' }}Indie House</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/indie-house-mark.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="listen-body {{ $user ? 'is-member' : 'is-guest' }}">
    {{-- Strings for app.js. In-app visits read this again, so a language change applies without reloading the player. --}}
    <script type="application/json" id="ih-messages" data-locale="{{ app()->getLocale() }}">@json(__('listen_client'))</script>
    <a class="skip-link" href="#main" data-i18n="skip">{{ __('listen.skip_to_content') }}</a>
    <div class="nav-progress" aria-hidden="true"></div>

    <div class="app-shell">
        <header class="topbar">
            <a class="brand topbar__brand" href="{{ route('listen.home') }}" aria-label="{{ __('listen.home_link') }}">
                <img class="brand__mark" src="{{ asset('images/indie-house-mark.svg') }}" alt="" aria-hidden="true">
                <span class="brand__word">indie house</span>
            </a>

            <div class="topbar__history">
                <button class="icon-button icon-button--dim" type="button" data-history-back aria-label="{{ __('listen.back') }}" title="{{ __('listen.back') }}">{{ Icon::svg('back') }}</button>
                <button class="icon-button icon-button--dim" type="button" data-history-forward aria-label="{{ __('listen.forward') }}" title="{{ __('listen.forward') }}">{{ Icon::svg('forward') }}</button>
            </div>

            <div class="topbar__center">
                <a class="topbar__home {{ request()->routeIs('listen.home') ? 'is-active' : '' }}" href="{{ route('listen.home') }}" aria-label="{{ __('listen.nav.home') }}" title="{{ __('listen.nav.home') }}" @if(request()->routeIs('listen.home')) aria-current="page" @endif>{{ Icon::svg('home') }}</a>
                <form class="search-box topbar__search" action="{{ route('listen.search') }}" method="get" role="search" data-live-search-form>
                    <label class="sr-only" for="site-search">{{ __('listen.search_box.label') }}</label>
                    {{ Icon::svg('search', 'search-box__icon') }}
                    <input id="site-search" name="q" value="{{ request()->routeIs('listen.search') ? request('q') : '' }}" placeholder="{{ __('listen.search_box.placeholder') }}" autocomplete="off" spellcheck="false" data-live-search aria-controls="search-suggestions" aria-expanded="false" aria-autocomplete="list">
                    <kbd class="search-box__kbd" aria-hidden="true">/</kbd>
                    <a class="search-box__browse {{ request()->routeIs('listen.catalogue') ? 'is-active' : '' }}" href="{{ route('listen.catalogue') }}" aria-label="{{ __('listen.nav.browse') }}" title="{{ __('listen.nav.browse') }}" @if(request()->routeIs('listen.catalogue')) aria-current="page" @endif>{{ Icon::svg('compass') }}</a>
                    <div class="search-suggestions" id="search-suggestions" data-search-suggestions role="region" aria-label="{{ __('listen.search_box.suggestions') }}" hidden>
                        <p class="search-suggestions__status" data-search-status role="status"></p>
                        <div data-search-list></div>
                        <button class="search-suggestions__all" type="submit">{{ __('listen.search_box.all_results') }} {{ Icon::svg('arrow-up-right') }}</button>
                    </div>
                </form>
            </div>

            <div class="topbar__account">
                @include('listen.components.locale-switch')
                @auth
                    <details class="account-menu" data-account-menu>
                        <summary aria-label="{{ __('listen.account.menu', ['name' => $user->name]) }}" title="{{ $user->name }}">
                            <span class="avatar" aria-hidden="true">
                                @if ($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="">
                                @else
                                    {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                                @endif
                            </span>
                        </summary>
                        <div class="menu-panel account-menu__panel">
                            <div class="account-menu__who">
                                <strong>{{ $user->name }}</strong>
                                <span>{{ $user->email }}</span>
                            </div>
                            <a class="menu-item" href="{{ route('library.index') }}">{{ Icon::svg('library') }}<span>{{ __('listen.nav.library') }}</span></a>
                            <a class="menu-item" href="{{ route('settings.show') }}">{{ Icon::svg('sliders') }}<span>{{ __('settings.title') }}</span></a>
                            @if ($canAccessAdmin)
                                <a class="menu-item" href="{{ url('/admin') }}" data-player-navigation="false">{{ Icon::svg('shield') }}<span>{{ __('listen.account.admin') }}</span>{{ Icon::svg('arrow-up-right', 'menu-item__trail') }}</a>
                            @endif
                            <hr class="menu-divider">
                            <form action="{{ route('logout') }}" method="post">
                                @csrf
                                <button class="menu-item" type="submit">{{ Icon::svg('logout') }}<span>{{ __('listen.account.logout') }}</span></button>
                            </form>
                        </div>
                    </details>
                @else
                    @if (config('instance.registration_mode') === 'open')
                        <a class="topbar__signup" href="{{ route('register') }}">{{ __('listen.account.register') }}</a>
                    @endif
                    <a class="button button--light" href="{{ route('login') }}">{{ __('listen.account.login') }}</a>
                @endauth
            </div>
        </header>

        <aside class="library-panel" aria-label="{{ __('listen.sidebar.label') }}">
            <div class="library-panel__head">
                <a class="library-panel__title {{ request()->routeIs('library.*') ? 'is-active' : '' }}" href="{{ $libraryUrl }}" title="{{ __('listen.sidebar.label') }}">
                    {{ Icon::svg('library') }}<span>{{ __('listen.sidebar.label') }}</span>
                </a>
                @auth
                    <a class="icon-button icon-button--sm library-panel__add" href="{{ route('library.index') }}#create-playlist" aria-label="{{ __('listen.sidebar.new_playlist') }}" title="{{ __('listen.sidebar.new_playlist') }}">{{ Icon::svg('plus') }}</a>
                @endauth
            </div>

            @auth
                <nav class="library-panel__list" aria-label="{{ __('listen.sidebar.playlists') }}">
                    <a class="library-item library-item--history" href="{{ route('library.index') }}#history" title="{{ __('listen.sidebar.history') }}">
                        <span class="art library-item__art" data-tone="0">{{ Icon::svg('history', 'art__icon') }}</span>
                        <span class="library-item__copy"><strong>{{ __('listen.sidebar.history') }}</strong><small>{{ __('listen.sidebar.history_hint') }}</small></span>
                    </a>
                    @foreach ($userPlaylists as $playlist)
                        <a class="library-item {{ request()->routeIs('playlists.show') && request()->route('playlist')?->is($playlist) ? 'is-active' : '' }}" href="{{ route('playlists.show', $playlist) }}" data-context-link="playlist:{{ $playlist->public_id }}" title="{{ $playlist->title }}">
                            @include('listen.components.art', ['seed' => $playlist->public_id, 'glyph' => $playlist->title, 'class' => 'library-item__art'])
                            <span class="library-item__copy">
                                <strong>{{ $playlist->title }}</strong>
                                <small>@unless($playlist->is_public){{ Icon::svg('lock', 'library-item__lock') }}@endunless {{ trans_choice('listen.track_count', $playlist->tracks_count) }}</small>
                            </span>
                            <span class="library-item__live" aria-hidden="true">{{ Icon::svg('volume') }}</span>
                        </a>
                    @endforeach
                </nav>
                @if ($userPlaylists->isEmpty())
                    <div class="library-promo">
                        <p class="library-promo__title">{{ __('listen.sidebar.empty_title') }}</p>
                        <p>{{ __('listen.sidebar.empty_body') }}</p>
                        <a class="button button--light button--sm" href="{{ route('library.index') }}#create-playlist">{{ __('listen.sidebar.new_playlist') }}</a>
                    </div>
                @endif
            @else
                <div class="library-panel__list library-panel__list--guest">
                    <div class="library-promo">
                        <p class="library-promo__title">{{ __('listen.sidebar.guest_title') }}</p>
                        <p>{{ __('listen.sidebar.guest_body') }}</p>
                        <a class="button button--light button--sm" href="{{ route('login', ['intended' => route('library.index')]) }}">{{ __('listen.account.login') }}</a>
                    </div>
                    <div class="library-promo library-promo--quiet">
                        <p class="library-promo__title">{{ __('listen.sidebar.preview_title') }}</p>
                        <p>{{ __('listen.sidebar.preview_body') }}</p>
                    </div>
                </div>
            @endauth
        </aside>

        <main class="content-panel" id="main" tabindex="-1">
            @if (session('status'))
                <div class="toast" role="status" data-toast>{{ Icon::svg('check') }}<span>{{ session('status') }}</span></div>
            @endif

            @yield('content')

            <footer class="site-footer">
                <p class="site-footer__brand"><img src="{{ asset('images/indie-house-mark.svg') }}" alt="" aria-hidden="true"> Indie House</p>
                <nav aria-label="{{ __('listen.nav.footer') }}">
                    <a href="{{ route('listen.catalogue') }}">{{ __('listen.nav.browse') }}</a>
                    <a href="{{ $libraryUrl }}">{{ __('listen.nav.library') }}</a>
                    @auth<a href="{{ route('settings.show') }}">{{ __('settings.title') }}</a>@endauth
                    <a href="{{ route('legal.terms') }}">{{ __('legal.terms_link') }}</a>
                    <a href="{{ route('legal.privacy') }}">{{ __('legal.privacy_link') }}</a>
                </nav>
            </footer>
        </main>
    </div>

    <aside class="queue-panel" data-queue-panel aria-labelledby="queue-title" hidden>
        <div class="queue-panel__head">
            <h2 id="queue-title" data-i18n="queue-title">{{ __('listen.queue.title') }}</h2>
            <button class="icon-button icon-button--sm" type="button" data-queue-close aria-label="{{ __('listen.queue.close') }}" data-i18n="queue-close">{{ Icon::svg('close') }}</button>
        </div>
        <div class="queue-panel__body">
            <p class="eyebrow" data-i18n="queue-now">{{ __('listen.queue.now_playing') }}</p>
            <div data-queue-current></div>
            <p class="eyebrow queue-panel__next"><span data-i18n="queue-next">{{ __('listen.queue.next') }}</span><span data-queue-context></span></p>
            <ol class="queue-list" data-queue-list></ol>
            <p class="queue-panel__empty" data-queue-empty data-i18n="queue-empty">{{ __('listen.queue.empty') }}</p>
        </div>
    </aside>

    <section class="global-player" data-player
        data-history-url="{{ $user ? route('listening-history.store') : '' }}"
        data-audio-quality="{{ $user?->preference?->audio_quality ?? 'auto' }}"
        data-autoplay="{{ ($user?->preference?->autoplay ?? true) ? '1' : '0' }}"
        data-record-history="{{ ($user?->preference?->record_listening_history ?? true) ? '1' : '0' }}"
        data-guest="{{ $user ? '0' : '1' }}"
        data-icon-play="{{ Icon::paths('play') }}"
        data-icon-pause="{{ Icon::paths('pause') }}"
        hidden aria-label="{{ __('listen.player.label') }}" data-i18n="player">
        <audio data-player-audio preload="none"></audio>
        <div class="global-player__backdrop" aria-hidden="true"><img alt="" data-player-backdrop></div>
        <div class="global-player__mini-progress" aria-hidden="true"><span data-player-mini-progress></span></div>

        <div class="global-player__sheet-head">
            <button class="icon-button" type="button" data-player-collapse aria-label="{{ __('listen.player.collapse') }}" data-i18n="player-collapse">{{ Icon::svg('chevron-down') }}</button>
            <p><span data-i18n="player-from">{{ __('listen.player.playing_from') }}</span><strong data-player-context>Indie House</strong></p>
            <span class="global-player__sheet-spacer" aria-hidden="true"></span>
        </div>

        <div class="global-player__track">
            <span class="global-player__art" data-player-art>@include('listen.components.art', ['seed' => 'player', 'icon' => 'note'])</span>
            <div class="global-player__details">
                <a class="global-player__title" href="{{ route('listen.home') }}" data-player-title>{{ __('listen.player.idle_title') }}</a>
                <a class="global-player__artist" href="{{ route('listen.catalogue') }}" data-player-artist>Indie House</a>
                @guest<span class="global-player__badge" title="{{ __('listen.player.preview_hint') }}" data-i18n="player-badge">{{ __('listen.player.preview_badge') }}</span>@endguest
            </div>
            <button class="global-player__expand" type="button" data-player-expand aria-label="{{ __('listen.player.expand') }}" aria-expanded="false" data-i18n="player-expand"></button>
        </div>

        <div class="global-player__transport">
            <div class="global-player__buttons">
                <button class="player-button global-player__mode" type="button" data-player-shuffle aria-label="{{ __('listen_client.shuffle_on') }}" aria-pressed="false" title="{{ __('listen_client.shuffle') }}">{{ Icon::svg('shuffle') }}</button>
                <button class="player-button" type="button" data-player-previous aria-label="{{ __('listen_client.previous') }}" title="{{ __('listen_client.previous') }}">{{ Icon::svg('previous') }}</button>
                <button class="player-button player-button--main" type="button" data-player-toggle aria-label="{{ __('listen_client.play') }}" title="{{ __('listen_client.play') }}"><svg class="icon icon--fill" viewBox="0 0 24 24" aria-hidden="true" focusable="false" data-player-toggle-icon>{!! Icon::paths('play') !!}</svg></button>
                <button class="player-button" type="button" data-player-next aria-label="{{ __('listen_client.next') }}" title="{{ __('listen_client.next') }}">{{ Icon::svg('next') }}</button>
                <button class="player-button global-player__mode" type="button" data-player-repeat aria-label="{{ __('listen_client.repeat_turn_on') }}" aria-pressed="false" title="{{ __('listen_client.repeat_off') }}">{{ Icon::svg('repeat') }}<span class="global-player__repeat-one" data-player-repeat-one hidden>1</span></button>
            </div>
            <div class="global-player__seek">
                <time data-player-current>0:00</time>
                <input class="range" type="range" min="0" max="100" step="0.1" value="0" data-player-seek aria-label="{{ __('listen.player.seek') }}" aria-valuetext="{{ __('listen_client.seek_value', ['current' => '0:00', 'total' => '0:00']) }}" data-i18n="player-seek">
                <time data-player-duration>0:00</time>
            </div>
        </div>

        <div class="global-player__extras">
            <button class="player-button player-button--sm" type="button" data-queue-toggle aria-label="{{ __('listen_client.queue_open') }}" aria-pressed="false" title="{{ __('listen.queue.title') }}" data-i18n="player-queue">{{ Icon::svg('queue') }}</button>
            <div class="global-player__volume">
                <button class="player-button player-button--sm" type="button" data-player-mute aria-label="{{ __('listen_client.mute') }}" title="{{ __('listen_client.mute') }}">
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" data-player-volume-icon data-icon-volume="{{ Icon::paths('volume') }}" data-icon-low="{{ Icon::paths('volume-low') }}" data-icon-mute="{{ Icon::paths('volume-mute') }}">{!! Icon::paths('volume') !!}</svg>
                </button>
                <label class="sr-only" for="player-volume" data-i18n="player-volume">{{ __('listen.player.volume') }}</label>
                <input class="range range--volume" id="player-volume" type="range" min="0" max="1" step="0.05" value="1" data-player-volume>
            </div>
        </div>

        <p class="global-player__error" data-player-error role="status" hidden></p>
    </section>

    @guest
        <aside class="guest-bar" aria-label="{{ __('listen.guest_bar.label') }}" data-i18n="guest-bar">
            <p><strong data-i18n="guest-title">{{ __('listen.guest_bar.title') }}</strong><span data-i18n="guest-body">{{ __('listen.guest_bar.body') }}</span></p>
            <a class="button button--light button--sm" href="{{ route('login') }}" data-i18n="guest-login">{{ __('listen.account.login') }}</a>
        </aside>
    @endguest

    <nav class="mobile-nav" aria-label="{{ __('listen.nav.main') }}">
        <a class="{{ request()->routeIs('listen.home') ? 'is-active' : '' }}" href="{{ route('listen.home') }}" @if(request()->routeIs('listen.home')) aria-current="page" @endif>{{ Icon::svg('home') }}<span>{{ __('listen.nav.home') }}</span></a>
        <a class="{{ request()->routeIs('listen.search') ? 'is-active' : '' }}" href="{{ route('listen.search') }}" @if(request()->routeIs('listen.search')) aria-current="page" @endif>{{ Icon::svg('search') }}<span>{{ __('listen.nav.search') }}</span></a>
        <a class="{{ request()->routeIs('listen.catalogue', 'artists.*', 'albums.*') ? 'is-active' : '' }}" href="{{ route('listen.catalogue') }}" @if(request()->routeIs('listen.catalogue')) aria-current="page" @endif>{{ Icon::svg('compass') }}<span>{{ __('listen.nav.browse') }}</span></a>
        <a class="{{ request()->routeIs('library.*', 'playlists.*', 'settings.*') ? 'is-active' : '' }}" href="{{ $libraryUrl }}" @if(request()->routeIs('library.*')) aria-current="page" @endif>{{ Icon::svg('library') }}<span>{{ __('listen.nav.library') }}</span></a>
    </nav>
</body>
</html>
