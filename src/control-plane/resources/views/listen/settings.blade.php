@extends('listen.layouts.app', ['title' => __('settings.title')])

@use('App\Support\Icon')

@section('content')
    @php
        $user = auth()->user();
        $avatarUrl = $user->avatarUrl();
        // "auto" has always played 128 kbps; a lossless preference whose grant
        // was revoked plays 320 kbps.
        $quality = match ($saved = old('audio_quality', $preferences?->audio_quality ?? 'auto')) {
            'auto' => 'standard',
            'lossless' => $losslessAvailable ? 'lossless' : 'high',
            default => $saved,
        };
        $autoplay = (bool) old('autoplay', $preferences?->autoplay ?? true);
        $recordHistory = (bool) old('record_listening_history', $preferences?->record_listening_history ?? true);
        $sections = [
            'account' => __('settings.account'),
            'playback' => __('settings.playback'),
            'language' => __('settings.language'),
            'security' => __('settings.security'),
            'devices' => __('settings.devices'),
            'privacy' => __('settings.privacy'),
        ];
    @endphp

    <header class="page-head page-head--tinted">
        <h1 class="page-head__title">{{ __('settings.title') }}</h1>
    </header>

    <div class="settings-layout">
        <nav class="settings-nav" aria-label="{{ __('settings.sections') }}">
            @foreach ($sections as $id => $label)
                <a href="#settings-{{ $id }}" data-player-navigation="false">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="settings-sections">
            <section class="settings-section" id="settings-account" aria-labelledby="settings-account-title">
                <header class="settings-section__head">
                    <h2 id="settings-account-title">{{ __('settings.profile_heading') }}</h2>
                    <p>{{ __('settings.profile_description') }}</p>
                </header>
                <form class="form-stack" action="{{ route('settings.profile') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    @method('put')
                    <div class="avatar-field" data-avatar-field>
                        <span class="avatar avatar--xl" data-avatar-preview>
                            @if ($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ __('settings.avatar') }}">
                            @else
                                {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                            @endif
                        </span>
                        <div>
                            <label class="button button--outline button--sm" for="settings-avatar">{{ __('settings.change_avatar') }}</label>
                            <input class="sr-only" id="settings-avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-input>
                            <p class="field-hint">{{ __('settings.avatar_hint') }}</p>
                            @error('avatar') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="field-grid">
                        <div class="field">
                            <label for="settings-name">{{ __('settings.display_name') }}</label>
                            <input id="settings-name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" @error('name') aria-invalid="true" @enderror>
                            @error('name') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                        </div>
                        <div class="field">
                            <label for="settings-email">{{ __('settings.email') }}</label>
                            <input id="settings-email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                            @error('email') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="settings-profile-password">{{ __('settings.email_password') }}</label>
                        <div class="password-field">
                            <input id="settings-profile-password" type="password" name="current_password" autocomplete="current-password" @error('current_password') aria-invalid="true" @enderror>
                            <button class="icon-button icon-button--sm" type="button" data-password-toggle data-label-show="{{ __('settings.show_password') }}" data-label-hide="{{ __('settings.hide_password') }}" aria-label="{{ __('settings.show_password') }}">{{ Icon::svg('eye') }}</button>
                        </div>
                        @error('current_password') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                    </div>
                    <div class="form-actions"><button class="button button--primary" type="submit">{{ __('settings.save_profile') }}</button></div>
                </form>
            </section>

            <section class="settings-section" id="settings-playback" aria-labelledby="settings-playback-title">
                <header class="settings-section__head">
                    <h2 id="settings-playback-title">{{ __('settings.quality_heading') }}</h2>
                    <p>{{ __('settings.quality_description') }}</p>
                </header>
                <form class="form-stack" action="{{ route('settings.playback') }}" method="post">
                    @csrf
                    @method('put')
                    <fieldset class="choice-grid">
                        <legend class="sr-only">{{ __('settings.default_quality') }}</legend>
                        @foreach ($losslessAvailable ? ['standard', 'high', 'lossless'] : ['standard', 'high'] as $choice)
                            <label class="choice-card">
                                <input type="radio" name="audio_quality" value="{{ $choice }}" @checked($quality === $choice)>
                                <span class="choice-card__body">
                                    <strong>{{ __('settings.quality_'.$choice) }}</strong>
                                    <small>{{ __('settings.quality_'.$choice.'_hint') }}</small>
                                </span>
                                <span class="choice-card__check" aria-hidden="true">{{ Icon::svg('check') }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                    @error('audio_quality') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                    <input type="hidden" name="autoplay" value="0">
                    <label class="switch-row" for="settings-autoplay">
                        <span><strong>{{ __('settings.autoplay') }}</strong><small>{{ __('settings.autoplay_hint') }}</small></span>
                        <input class="switch" id="settings-autoplay" type="checkbox" role="switch" name="autoplay" value="1" @checked($autoplay)>
                    </label>
                    <div class="form-actions"><button class="button button--primary" type="submit">{{ __('settings.save_playback') }}</button></div>
                </form>
            </section>

            <section class="settings-section" id="settings-language" aria-labelledby="settings-language-title">
                <header class="settings-section__head">
                    <h2 id="settings-language-title">{{ __('settings.language_heading') }}</h2>
                    <p>{{ __('settings.language_description') }}</p>
                </header>
                <form class="form-stack" action="{{ route('locale.update') }}" method="post" data-locale-form>
                    @csrf
                    <fieldset class="choice-grid choice-grid--compact">
                        <legend class="sr-only">{{ __('settings.language') }}</legend>
                        @foreach (config('app.supported_locales') as $code)
                            <button class="choice-card" type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}">
                                <span class="choice-card__body"><strong>{{ __('listen.locale.'.$code) }}</strong></span>
                                <span class="choice-card__check" aria-hidden="true">{{ Icon::svg('check') }}</span>
                            </button>
                        @endforeach
                    </fieldset>
                </form>
            </section>

            <section class="settings-section" id="settings-security" aria-labelledby="settings-security-title">
                <header class="settings-section__head">
                    <h2 id="settings-security-title">{{ __('settings.change_password') }}</h2>
                    <p>{{ __('settings.password_description') }}</p>
                </header>
                <form class="form-stack" action="{{ route('settings.password') }}" method="post">
                    @csrf
                    @method('put')
                    @foreach ([['current-password', 'current_password', 'current_password', 'current-password'], ['new-password', 'password', 'new_password', 'new-password'], ['confirm-password', 'password_confirmation', 'confirm_password', 'new-password']] as [$id, $name, $label, $autocomplete])
                        <div class="field">
                            <label for="{{ $id }}">{{ __('settings.'.$label) }}</label>
                            <div class="password-field">
                                <input id="{{ $id }}" type="password" name="{{ $name }}" required autocomplete="{{ $autocomplete }}" @error($name) aria-invalid="true" @enderror>
                                <button class="icon-button icon-button--sm" type="button" data-password-toggle data-label-show="{{ __('settings.show_password') }}" data-label-hide="{{ __('settings.hide_password') }}" aria-label="{{ __('settings.show_password') }}">{{ Icon::svg('eye') }}</button>
                            </div>
                            @if ($name !== 'password_confirmation')
                                @error($name) <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
                            @endif
                        </div>
                    @endforeach
                    <div class="form-actions"><button class="button button--primary" type="submit">{{ __('settings.save_password') }}</button></div>
                </form>
            </section>

            <section class="settings-section" id="settings-devices" aria-labelledby="settings-devices-title">
                <header class="settings-section__head">
                    <h2 id="settings-devices-title">{{ __('settings.mobile_sessions') }}</h2>
                    <p>{{ __('settings.devices_description') }}</p>
                </header>
                @forelse ($sessions as $session)
                    <div class="device-row">
                        <span class="device-row__icon" aria-hidden="true">{{ Icon::svg('device') }}</span>
                        <div class="device-row__copy">
                            <strong>{{ $session->device_name ?: ($session->platform ?: __('settings.unknown_device')) }}</strong>
                            <span class="mono">{{ $session->platform ?: __('settings.unknown_platform') }} · {{ __('settings.last_used') }} {{ $session->last_used_at?->diffForHumans() ?: __('settings.never_used') }}</span>
                        </div>
                        <form action="{{ route('settings.devices.revoke', $session) }}" method="post" data-confirm="{{ __('settings.confirm_revoke') }}">
                            @csrf
                            @method('delete')
                            <button class="button button--outline button--sm button--danger" type="submit"><span data-confirm-label>{{ __('settings.revoke') }}</span></button>
                        </form>
                    </div>
                @empty
                    <p class="settings-empty">{{ Icon::svg('device') }}{{ __('settings.no_devices') }}</p>
                @endforelse
            </section>

            <section class="settings-section" id="settings-privacy" aria-labelledby="settings-privacy-title">
                <header class="settings-section__head">
                    <h2 id="settings-privacy-title">{{ __('settings.history_heading') }}</h2>
                    <p>{{ __('settings.history_description') }} <a class="settings-legal-link" href="{{ route('legal.privacy') }}">{{ __('legal.privacy.title') }}</a></p>
                </header>
                <form class="form-stack" action="{{ route('settings.privacy') }}" method="post">
                    @csrf
                    @method('put')
                    <input type="hidden" name="record_listening_history" value="0">
                    <label class="switch-row" for="settings-history">
                        <span><strong>{{ __('settings.record_history') }}</strong><small>{{ __('settings.record_history_hint') }}</small></span>
                        <input class="switch" id="settings-history" type="checkbox" role="switch" name="record_listening_history" value="1" @checked($recordHistory)>
                    </label>
                    <div class="form-actions"><button class="button button--primary" type="submit">{{ __('settings.save_privacy') }}</button></div>
                </form>
                <div class="export-row">
                    <div>
                        <h3>{{ __('settings.export_heading') }}</h3>
                        <p>{{ __('settings.export_description') }}</p>
                    </div>
                    <a class="button button--outline button--sm" href="{{ route('settings.export') }}" data-player-navigation="false">{{ Icon::svg('download') }}{{ __('settings.export_button') }}</a>
                </div>
            </section>
        </div>
    </div>
@endsection
