@php use App\Support\Format; @endphp
<x-layouts.app title="Load lists">
    <x-ui.page-header title="Load lists" description="Pick, load, check and dispatch equipment for each event." :breadcrumbs="['Operations' => null, 'Load lists' => null]" />
    <x-ui.card :padding="false">
        <nav class="flex gap-1 border-b border-ink-100 px-4 sm:px-6" aria-label="Filter">
            @foreach (['open' => 'To dispatch', 'dispatched' => 'Dispatched'] as $key => $label)
                <a href="{{ route('app.load-lists.index', ['filter' => $key]) }}" @if ($filter === $key) aria-current="page" @endif @class(['-mb-px border-b-2 px-3 py-3 text-sm font-semibold', 'border-brand-600 text-ink-900' => $filter === $key, 'border-transparent text-ink-500' => $filter !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        @if ($lists->isEmpty())
            <x-ui.empty-state icon="clipboard-list" title="No load lists here" description="Create a load list from an event's Allocation tab." />
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($lists as $list)
                    <li><a href="{{ route('app.events.load-list', $list->event) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                        <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $list->event->name }}</span><span class="block text-xs text-ink-500"><span class="font-mono">{{ $list->reference }}</span> · setup {{ Format::datetime($list->event->setup_starts_at, 'D j M, g:ia') }}</span></span>
                        <span class="text-sm tabular-nums text-ink-600">{{ $list->checked_count }}/{{ $list->items_count }} checked</span>
                        <x-ui.badge :tone="$list->status->tone()">{{ $list->status->label() }}</x-ui.badge>
                    </a></li>
                @endforeach
            </ul>
            @if ($lists->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $lists->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
