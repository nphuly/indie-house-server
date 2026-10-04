@extends('auth.layout', ['title' => __('auth.login_title')])

@use('App\Support\Icon')

@section('content')
    <h1 class="auth-card__title">{{ __('auth.login_title') }}</h1>
    <form class="form-stack auth-form" action="{{ route('login.store') }}" method="post">
        @csrf
        <div class="field">
            <label for="email">{{ __('auth.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p class="field-error" id="email-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password">{{ __('auth.password_label') }}</label>
            <div class="password-field">
                <input id="password" name="password" type="password" required autocomplete="current-password">
                <button class="icon-button icon-button--sm" type="button" data-password-toggle data-label-show="{{ __('auth.show_password') }}" data-label-hide="{{ __('auth.hide_password') }}" aria-label="{{ __('auth.show_password') }}">{{ Icon::svg('eye') }}</button>
            </div>
        </div>
        <label class="checkbox-label" for="remember">
            <input id="remember" name="remember" type="checkbox" value="1">
            {{ __('auth.remember') }}
        </label>
        <button class="button button--primary button--block" type="submit">{{ __('auth.login_button') }}</button>
    </form>
    @if (config('instance.registration_mode') === 'open')
        <p class="auth-switch">{{ __('auth.no_account') }} <a href="{{ route('register') }}">{{ __('auth.register_link') }}</a></p>
    @elseif (config('instance.registration_mode') === 'invite_only')
        <p class="auth-switch">{{ __('auth.invite_only') }}</p>
    @else
        <p class="auth-switch">{{ __('auth.closed') }}</p>
    @endif
@endsection
