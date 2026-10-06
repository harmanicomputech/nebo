<x-layouts.app title="Logistics">
    <x-ui.page-header title="Logistics" description="Trips to venues and back, with vehicles, drivers and crew." :breadcrumbs="['Operations' => null, 'Logistics' => null]">
        <x-slot:actions>
            @can('logistics.view')<x-ui.button variant="secondary" icon="car-front" :href="route('app.logistics.vehicles.index')">Fleet</x-ui.button>@endcan
            @can('create', App\Models\LogisticsTrip::class)<x-ui.button icon="plus" :href="route('app.logistics.trips.create', ['direction' => 'transfer'])">Transfer trip</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Trips today" :value="$stats['today']" icon="route" :href="route('app.logistics.index', ['view' => 'today'])" />
        <x-ui.stat label="On the road" :value="$stats['in_transit']" icon="truck" tone="brand" :href="route('app.logistics.index', ['view' => 'in_transit'])" />
        <x-ui.stat label="Missing vehicle or driver" :value="$stats['unassigned']" icon="triangle-alert" tone="warning" />
        @if ($stats['vehicles'] !== null)<x-ui.stat label="Vehicles in service" :value="$stats['vehicles']" icon="car-front" :href="route('app.logistics.vehicles.index')" />@endif
    </div>

    <x-ui.card :padding="false">
        <nav class="flex gap-1 overflow-x-auto border-b border-ink-100 px-4 sm:px-6" aria-label="View">
            @foreach ($views as $key => $label)
                <a href="{{ route('app.logistics.index', ['view' => $key]) }}" @if ($view === $key) aria-current="page" @endif
                   @class(['-mb-px shrink-0 border-b-2 px-3 py-3 text-sm font-semibold', 'border-brand-600 text-ink-900' => $view === $key, 'border-transparent text-ink-500 hover:text-ink-900' => $view !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-6 lg:items-end">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="relative sm:col-span-2">
                <label for="q" class="sr-only">Search trips</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, event, place or plate" class="field pl-9">
            </div>
            <x-ui.select name="direction" :options="$directions" :value="$filters['direction'] ?? ''" placeholder="Any direction" aria-label="Direction" />
            @if ($vehicles)<x-ui.select name="vehicle" :options="$vehicles" :value="$filters['vehicle'] ?? ''" placeholder="Any vehicle" aria-label="Vehicle" />@endif
            @if ($drivers)<x-ui.select name="driver" :options="$drivers" :value="$filters['driver'] ?? ''" placeholder="Any driver" aria-label="Driver" />@endif
            <div class="flex items-center gap-3">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                @if (collect($filters)->filter()->isNotEmpty())<a href="{{ route('app.logistics.index', ['view' => $view]) }}" class="text-sm font-semibold text-brand-700 hover:underline">Clear</a>@endif
            </div>
        </form>

        @if ($trips->isEmpty())
            <x-ui.empty-state icon="truck" title="No trips here" :description="auth()->user()->can('logistics.view') ? 'Plan trips from an event\'s Logistics tab.' : 'Trips you drive or crew will appear here.'" />
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($trips as $t)@include('internal.logistics.trips._row', ['t' => $t])@endforeach
            </ul>
            @if ($trips->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $trips->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
