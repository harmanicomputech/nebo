@php use App\Support\Format; @endphp
<x-layouts.app title="Quotations">
    <x-ui.page-header title="Quotations" description="Prepare, approve and send quotations; customers accept them online." :breadcrumbs="['Commercial' => null, 'Quotations' => null]">
        <x-slot:actions>
            @can('viewAny', App\Models\ProductionPackage::class)<x-ui.button variant="secondary" icon="package" :href="route('app.packages.index')">Packages</x-ui.button>@endcan
            @can('create', App\Models\Quotation::class)<x-ui.button icon="plus" :href="route('app.quotations.create')">New quotation</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Drafts" :value="$stats['drafts']" icon="pencil" :href="route('app.quotations.index', ['view' => 'draft'])" />
        <x-ui.stat label="Awaiting answer" :value="$stats['awaiting']" icon="send" tone="warning" :href="route('app.quotations.index', ['view' => 'sent'])" />
        @can('financial.view')
            <x-ui.stat label="Value awaiting" :value="Format::naira($stats['awaitingValue'])" icon="receipt" />
            <x-ui.stat label="Accepted this month" :value="Format::naira($stats['wonMonth'])" icon="circle-check" tone="success" />
        @endcan
    </div>

    <x-ui.card :padding="false">
        <nav class="flex gap-1 overflow-x-auto border-b border-ink-100 px-4 sm:px-6" aria-label="View">
            @foreach ($views as $key => $label)
                <a href="{{ route('app.quotations.index', ['view' => $key]) }}" @if ($view === $key) aria-current="page" @endif
                   @class(['-mb-px shrink-0 border-b-2 px-3 py-3 text-sm font-semibold', 'border-brand-600 text-ink-900' => $view === $key, 'border-transparent text-ink-500 hover:text-ink-900' => $view !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="flex flex-col gap-3 border-b border-ink-100 p-4 sm:flex-row sm:px-6">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="relative flex-1">
                <label for="q" class="sr-only">Search quotations</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, title or customer" class="field pl-9">
            </div>
            <x-ui.button type="submit" variant="dark" icon="filter">Search</x-ui.button>
        </form>
        @if ($quotes->isEmpty())
            <x-ui.empty-state icon="receipt" title="No quotations here" description="Start one from a request, an event, a customer or a package." />
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($quotes as $q)
                    <li><a href="{{ route('app.quotations.show', $q) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-4 hover:bg-ink-50 sm:px-6">
                        <span class="min-w-0 flex-1 basis-56">
                            <span class="block truncate font-semibold">{{ $q->title }}</span>
                            <span class="block truncate text-xs text-ink-500"><span class="font-mono">{{ $q->label() }}</span> · {{ $q->customer->displayName() }} · {{ $q->preparer?->name }}</span>
                            <span class="mt-0.5 block text-xs text-ink-500">Valid until {{ Format::date($q->valid_until) }}@if ($q->viewed_at) · opened by customer @endif</span>
                        </span>
                        <span class="text-sm font-semibold tabular-nums">{{ Format::naira($q->total_kobo) }}</span>
                        <x-ui.badge :tone="$q->isExpired() ? 'warning' : $q->status->tone()">{{ $q->isExpired() ? 'Expired' : $q->status->label() }}</x-ui.badge>
                    </a></li>
                @endforeach
            </ul>
            @if ($quotes->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $quotes->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
