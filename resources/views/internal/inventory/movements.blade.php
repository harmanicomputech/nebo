<x-layouts.app title="Stock movements">
    <x-ui.page-header title="Stock movements" description="The inventory ledger: every addition, move, status and condition change, stock count and write-off. Entries can't be edited."
        :breadcrumbs="['Inventory' => null, 'Stock movements' => null]" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <x-ui.card title="Filter" class="lg:order-2">
            <form method="GET" class="space-y-4">
                <x-ui.input name="q" type="search" label="Search" :value="$filters['q'] ?? ''" placeholder="Equipment, SKU, tag or note" />
                <x-ui.select name="type" label="Movement" :options="$types" :value="$filters['type'] ?? ''" placeholder="Any" />
                <x-ui.select name="location" label="Location" :options="$locations" :value="$filters['location'] ?? ''" placeholder="Any" />
                <x-ui.input name="from" type="date" label="From" :value="$filters['from'] ?? ''" />
                <x-ui.input name="to" type="date" label="To" :value="$filters['to'] ?? ''" />
                <div class="flex gap-2">
                    <x-ui.button type="submit" variant="dark" icon="filter" class="flex-1">Filter</x-ui.button>
                    @if (array_filter($filters))<x-ui.button variant="ghost" :href="route('app.inventory.movements')">Clear</x-ui.button>@endif
                </div>
            </form>
        </x-ui.card>
        <x-ui.card class="lg:order-1 lg:col-span-3">
            @if ($movements->isEmpty())
                <x-ui.empty-state icon="history" title="No movements found" description="Nothing matches these filters." />
            @else
                <ol>@foreach ($movements as $t)@include('internal.inventory._ledger-entry', ['t' => $t, 'showItem' => true])@endforeach</ol>
                @if ($movements->hasPages())<div class="mt-6 border-t border-ink-100 pt-4">{{ $movements->links() }}</div>@endif
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
