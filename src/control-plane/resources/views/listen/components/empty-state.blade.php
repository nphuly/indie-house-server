@use('App\Support\Icon')
@php
    $action ??= null;
    $actionUrl ??= null;
    $icon ??= 'signal';
@endphp

<section class="empty-state">
    <span class="empty-state__icon" aria-hidden="true">{{ Icon::svg($icon) }}</span>
    <div>
        <h2>{{ $title }}</h2>
        <p>{{ $message }}</p>
        @if ($action && $actionUrl)
            <a class="button button--light button--sm" href="{{ $actionUrl }}">{{ $action }}</a>
        @endif
    </div>
</section>
