@php use App\Support\Format; @endphp
<x-layouts.app title="Requests">
    <x-ui.page-header title="Production requests" description="Requests from the public form, through review, quotation and confirmation."
        :breadcrumbs="['Operations' => null, 'Requests' => null]">
        <x-slot:actions><x-ui.button variant="secondary" icon="eye" :href="route('requests.create')" target="_blank">Open the public form</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-3 gap-4">
        <x-ui.stat label="New" :value="$counts['new']" icon="inbox" tone="brand" :href="route('app.requests.index', ['status' => 'new', 'view' => 'all'])" />
        <x-ui.stat label="Open" :value="$counts['open']" icon="history" :href="route('app.requests.index')" />
        <x-ui.stat label="Won" :value="$counts['won']" icon="circle-check" tone="success" :href="route('app.requests.index', ['view' => 'won'])" />
    </div>

    <x-ui.card :padding="false">
        <nav class="flex gap-1 overflow-x-auto border-b border-ink-100 px-4 sm:px-6" aria-label="Request views">
            @foreach (['open' => 'Open', 'won' => 'Won', 'closed' => 'Cancelled & declined', 'all' => 'All'] as $key => $label)
                <a href="{{ route('app.requests.index', array_merge(request()->except(['view', 'page', 'status']), ['view' => $key])) }}" @if ($view === $key) aria-current="page" @endif
                   @class(['-mb-px shrink-0 border-b-2 px-3 py-3 text-sm font-semibold', 'border-brand-600 text-ink-900' => $view === $key, 'border-transparent text-ink-500 hover:text-ink-900' => $view !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-6 lg:items-end">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="relative sm:col-span-2">
                <label for="q" class="sr-only">Search requests</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, event, contact, company, email…" class="field pl-9">
            </div>
            <x-ui.select name="status" :options="$statuses" :value="$filters['status'] ?? ''" placeholder="Any status" aria-label="Status" />
            <x-ui.select name="assigned" :options="['me' => 'Assigned to me', 'none' => 'Unassigned']" :value="$filters['assigned'] ?? ''" placeholder="Anyone" aria-label="Assigned" />
            <x-ui.select name="service" :options="$services" :value="$filters['service'] ?? ''" placeholder="Any service" aria-label="Service" />
            <x-ui.select name="sort" :options="['newest' => 'Newest first', 'event_date' => 'Soonest event']" :value="$filters['sort'] ?? 'newest'" aria-label="Sort" />
            <div class="flex flex-wrap items-end gap-3 sm:col-span-2 lg:col-span-6">
                <x-ui.input name="from" type="date" label="Event from" :value="$filters['from'] ?? ''" />
                <x-ui.input name="to" type="date" label="Event to" :value="$filters['to'] ?? ''" />
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                @if (collect($filters)->except(['view', 'sort'])->filter()->isNotEmpty())<a href="{{ route('app.requests.index', ['view' => $view]) }}" class="pb-2.5 text-sm font-semibold text-brand-700 hover:underline">Clear</a>@endif
            </div>
        </form>

        @if ($requests->isEmpty())
            <x-ui.empty-state icon="inbox" title="No requests found" description="New requests from the public form appear here." />
        @else
            <x-ui.table>
                <x-slot:head><th>Request</th><th class="hidden md:table-cell">Client</th><th>Event date</th><th>Status</th><th class="hidden lg:table-cell">Assigned</th></x-slot:head>
                @foreach ($requests as $r)
                    <tr>
                        <td class="min-w-48"><a href="{{ route('app.requests.show', $r) }}" class="font-semibold text-ink-900 hover:text-brand-700">{{ $r->event_name }}</a>
                            <span class="block font-mono text-xs text-ink-500">{{ $r->reference }} · {{ $r->created_at->diffForHumans() }}</span></td>
                        <td class="hidden md:table-cell">{{ $r->company ?: $r->contact_person }}<span class="block text-xs text-ink-500">{{ $r->company ? $r->contact_person : $r->email }}</span></td>
                        <td class="whitespace-nowrap">{{ Format::date($r->event_date) }}</td>
                        <td><x-ui.badge :tone="$r->status->tone()">{{ $r->status->label() }}</x-ui.badge></td>
                        <td class="hidden whitespace-nowrap text-ink-600 lg:table-cell">{{ $r->assignee?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
            @if ($requests->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $requests->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
