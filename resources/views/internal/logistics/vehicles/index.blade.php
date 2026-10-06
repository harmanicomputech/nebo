@php use App\Support\Format; @endphp
<x-layouts.app title="Fleet">
    <x-ui.page-header title="Fleet" description="Trucks, vans and buses, with drivers, papers and bookings." :breadcrumbs="['Operations' => null, 'Logistics' => route('app.logistics.index'), 'Fleet' => null]">
        <x-slot:actions>
            @can('create', App\Models\Vehicle::class)<x-ui.button icon="plus" :href="route('app.logistics.vehicles.create')">Add vehicle</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" data-filters class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5 lg:items-end">
            <div class="relative sm:col-span-2">
                <label for="q" class="sr-only">Search vehicles</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Name or registration" class="field pl-9">
            </div>
            <x-ui.select name="type" :options="$types" :value="$filters['type'] ?? ''" placeholder="Any type" aria-label="Type" />
            <x-ui.select name="status" :options="$statuses" :value="$filters['status'] ?? ''" placeholder="Any status" aria-label="Status" />
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                <label class="flex items-center gap-1.5 text-sm text-ink-600"><input type="checkbox" name="archived" value="1" @checked($filters['archived'] ?? false) class="size-4 rounded border-ink-300">Archived</label>
            </div>
        </form>
        @if ($vehicles->isEmpty())
            <x-ui.empty-state icon="car-front" title="No vehicles" description="Add the trucks and vans you use for deliveries." />
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($vehicles as $v)
                    @php $papers = $v->expiringPapers(); @endphp
                    <li><a href="{{ route('app.logistics.vehicles.show', $v) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-4 hover:bg-ink-50 sm:px-6">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink-950 text-white"><x-ui.icon name="truck" class="size-5" /></span>
                        <span class="min-w-0 flex-1 basis-48">
                            <span class="block font-semibold">{{ $v->name }} <span class="font-mono text-sm font-normal text-ink-500">{{ $v->registration }}</span></span>
                            <span class="block text-xs text-ink-500">{{ $v->typeLabel() }}@if ($v->capacity) · {{ $v->capacity }}@endif · {{ $v->defaultDriver?->name ?? 'No regular driver' }}@if ($v->baseLocation) · {{ $v->baseLocation->name }}@endif</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-2">
                            @foreach ($papers as $p)<x-ui.badge tone="danger">{{ $p }} due</x-ui.badge>@endforeach
                            @if ($v->on_road)<x-ui.badge tone="brand">On the road</x-ui.badge>@endif
                            <span class="text-xs text-ink-500">{{ $v->upcoming_trips_count }} booked</span>
                            <x-ui.badge :tone="$v->status->tone()">{{ $v->status->label() }}</x-ui.badge>
                        </span>
                    </a></li>
                @endforeach
            </ul>
            @if ($vehicles->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $vehicles->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
