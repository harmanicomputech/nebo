@php use App\Support\Format; @endphp
<x-layouts.app title="Customers">
    <x-ui.page-header title="Customers" description="Everyone who has asked for, booked or been quoted a production." :breadcrumbs="['Commercial' => null, 'Customers' => null]">
        <x-slot:actions>
            @can('create', App\Models\Customer::class)<x-ui.button icon="plus" :href="route('app.customers.create')">Add customer</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($reviewCount)
        <a href="{{ route('app.customers.index', ['review' => 1]) }}" class="mb-6 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 hover:bg-amber-100">
            <x-ui.icon name="triangle-alert" class="size-4 shrink-0" /><span class="flex-1">{{ $reviewCount }} profile(s) matched an existing customer with different details. Check and merge duplicates.</span><x-ui.icon name="arrow-right" class="size-4" />
        </a>
    @endif

    <x-ui.card :padding="false">
        <form method="GET" data-filters class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5 lg:items-end">
            <div class="relative sm:col-span-2">
                <label for="q" class="sr-only">Search customers</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Name, company, email or phone" class="field pl-9">
            </div>
            <x-ui.select name="type" :options="$types" :value="$filters['type'] ?? ''" placeholder="Any type" aria-label="Type" />
            <x-ui.select name="sort" :options="array_filter(['recent' => 'Recently active', 'name' => 'Name', 'value' => $seesMoney ? 'Accepted value' : null])" :value="$sort" aria-label="Sort" />
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                <label class="flex items-center gap-1.5 text-sm text-ink-600"><input type="checkbox" name="review" value="1" @checked($filters['review'] ?? false) class="size-4 rounded border-ink-300">To check</label>
            </div>
        </form>
        @if ($customers->isEmpty())
            <x-ui.empty-state icon="contact" title="No customers found" description="Customers are added automatically from booking requests, or by hand." />
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($customers as $c)
                    <li><a href="{{ route('app.customers.show', $c) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-4 hover:bg-ink-50 sm:px-6">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ink-900 text-sm font-semibold text-white">{{ mb_strtoupper(mb_substr($c->company ?: $c->name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1 basis-48">
                            <span class="block truncate font-semibold">{{ $c->company ?: $c->name }}@if ($c->needs_review) <x-ui.badge tone="warning" class="ml-1">Check</x-ui.badge>@endif</span>
                            <span class="block truncate text-xs text-ink-500">{{ collect([$c->company ? $c->name : null, $c->typeLabel(), $c->city, $c->email, $c->phone])->filter()->implode(' · ') }}</span>
                        </span>
                        <span class="text-xs text-ink-500">{{ $c->requests_count }} req · {{ $c->events_count }} events · {{ $c->quotations_count }} quotes</span>
                        @if ($seesMoney && $c->won_kobo)<span class="text-sm font-semibold tabular-nums">{{ Format::naira((int) $c->won_kobo) }}</span>@endif
                    </a></li>
                @endforeach
            </ul>
            @if ($customers->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $customers->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
