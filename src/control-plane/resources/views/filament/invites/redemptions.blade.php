<div class="space-y-3">
    @forelse ($redemptions as $redemption)
        <div class="rounded-xl border border-white/10 p-4">
            <div class="font-semibold">{{ $redemption->user?->email ?? '—' }}</div>
            <div class="mt-1 text-sm text-gray-400">
                {{ $redemption->created_at?->format('d/m/Y H:i') }}
                @if ($redemption->ip_address) · {{ $redemption->ip_address }} @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400">{{ __('invite.no_redemptions') }}</p>
    @endforelse
</div>
