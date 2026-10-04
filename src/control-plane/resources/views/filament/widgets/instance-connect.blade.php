<x-filament-widgets::widget>
    <div class="ih-connect">
        <div class="ih-connect__copy">
            <p class="ih-connect__eyebrow">{{ __('dashboard.connect_eyebrow') }}</p>
            <h2>{{ __('dashboard.connect_title') }}</h2>
            <p>{{ __('dashboard.connect_description') }}</p>
            <a href="{{ $manifestUrl }}" target="_blank" rel="noopener noreferrer">{{ $manifestUrl }}</a>
        </div>
        <div class="ih-connect__qr">
            <img src="{{ $qrDataUri }}" alt="{{ __('dashboard.connect_qr_alt') }}">
        </div>
    </div>
</x-filament-widgets::widget>
