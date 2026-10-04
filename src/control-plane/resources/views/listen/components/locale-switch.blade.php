@php
    $current = app()->getLocale();
@endphp
{{-- Two-letter language toggle. app.js submits it in the background while music plays; without JS it posts and returns to the same page. --}}
<form class="locale-switch" action="{{ route('locale.update') }}" method="post" role="group" aria-label="{{ __('listen.locale.label') }}" data-locale-form>
    @csrf
    @foreach (config('app.supported_locales') as $code)
        <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}" aria-pressed="{{ $code === $current ? 'true' : 'false' }}" aria-label="{{ __('listen.locale.'.$code) }}" title="{{ __('listen.locale.'.$code) }}">{{ strtoupper($code) }}</button>
    @endforeach
</form>
