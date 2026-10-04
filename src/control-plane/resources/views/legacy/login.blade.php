@extends('legacy.layout')

@section('title', __('legacy.login_heading'))

@section('content')
    <div class="login-art"><img src="{{ asset('images/indie-house-mark.png') }}" alt="" aria-hidden="true"></div>
    <p class="eyebrow">{{ __('legacy.welcome_label') }}</p>
    <h1>{{ __('legacy.login_heading') }}</h1>
    <p class="intro">{{ __('legacy.login_description') }}</p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('legacy.login') }}">
        @csrf
        <label for="email">{{ __('legacy.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
        <label for="password">{{ __('legacy.password') }}</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="action" type="submit">{{ __('legacy.sign_in') }}</button>
    </form>
@endsection
