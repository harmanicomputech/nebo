@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p class="text-ink-500">
            Showing <span class="font-semibold text-ink-800">{{ $paginator->firstItem() }}</span>–<span class="font-semibold text-ink-800">{{ $paginator->lastItem() }}</span>
            of <span class="font-semibold text-ink-800">{{ $paginator->total() }}</span>
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="grid size-9 place-items-center rounded-lg text-ink-300" aria-disabled="true"><x-ui.icon name="chevron-left" class="size-4" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="grid size-9 place-items-center rounded-lg text-ink-600 hover:bg-ink-100" aria-label="Previous page"><x-ui.icon name="chevron-left" class="size-4" /></a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-ink-400">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="grid size-9 place-items-center rounded-lg bg-ink-900 font-semibold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="hidden size-9 place-items-center rounded-lg text-ink-600 hover:bg-ink-100 sm:grid">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="grid size-9 place-items-center rounded-lg text-ink-600 hover:bg-ink-100" aria-label="Next page"><x-ui.icon name="chevron-right" class="size-4" /></a>
            @else
                <span class="grid size-9 place-items-center rounded-lg text-ink-300" aria-disabled="true"><x-ui.icon name="chevron-right" class="size-4" /></span>
            @endif
        </div>
    </nav>
@endif
