@extends('auth.layout', ['title' => __('invite.title')])

@use('App\Support\Icon')

@section('content')
    <h1 class="auth-card__title">{{ __('invite.heading') }}</h1>
    <form class="form-stack auth-form" action="{{ route('invite.redeem') }}" method="post">
        @csrf
        <input type="hidden" name="invite_code" value="{{ $code }}">
        @error('invite_code') <p class="form-alert" role="alert">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
        <div class="field">
            <label for="name">{{ __('invite.name') }}</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" @error('name') aria-invalid="true" @enderror>
            @error('name') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="email">{{ __('invite.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" @error('email') aria-invalid="true" @enderror>
            @error('email') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password">{{ __('invite.password') }}</label>
            <div class="password-field">
                <input id="password" name="password" type="password" required autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                <button class="icon-button icon-button--sm" type="button" data-password-toggle data-label-show="{{ __('auth.show_password') }}" data-label-hide="{{ __('auth.hide_password') }}" aria-label="{{ __('auth.show_password') }}">{{ Icon::svg('eye') }}</button>
            </div>
            @error('password') <p class="field-error">{{ Icon::svg('alert') }}{{ $message }}</p> @enderror
        </div>
        <div class="field">
            <label for="password_confirmation">{{ __('invite.confirm_password') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        </div>
        <p class="auth-legal">{!! __('legal.consent', ['terms' => e(route('legal.terms')), 'privacy' => e(route('legal.privacy'))]) !!}</p>
        <button class="button button--primary button--block" type="submit">{{ __('invite.accept') }}</button>
    </form>
    <p class="auth-switch">{{ __('invite.have_account') }} <a href="{{ route('login') }}">{{ __('invite.login') }}</a></p>
@endsection
