@use('App\Support\Icon')
@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ __('listen.pagination.label') }}">
        @if ($paginator->onFirstPage())
            <span class="icon-button icon-button--outline is-disabled" aria-disabled="true" aria-label="{{ __('listen.pagination.previous') }}">{{ Icon::svg('back') }}</span>
        @else
            <a class="icon-button icon-button--outline" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('listen.pagination.previous') }}">{{ Icon::svg('back') }}</a>
        @endif

        <span class="pagination__status">{{ __('listen.pagination.status', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>

        @if ($paginator->hasMorePages())
            <a class="icon-button icon-button--outline" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('listen.pagination.next') }}">{{ Icon::svg('forward') }}</a>
        @else
            <span class="icon-button icon-button--outline is-disabled" aria-disabled="true" aria-label="{{ __('listen.pagination.next') }}">{{ Icon::svg('forward') }}</span>
        @endif
    </nav>
@endif
