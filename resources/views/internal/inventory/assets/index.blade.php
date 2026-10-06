<x-layouts.app title="Assets">
    <x-ui.page-header title="Assets" description="Every individually tracked unit, wherever it is."
        :breadcrumbs="['Inventory' => null, 'Assets' => null]" />

    <x-ui.card :padding="false">
        <form method="GET" data-filters class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-6 lg:items-end">
            <div class="relative sm:col-span-2">
                <label for="q" class="sr-only">Search assets</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Asset tag, serial, barcode or equipment" class="field pl-9">
            </div>
            <x-ui.select name="category" :options="$categories" :value="$filters['category'] ?? ''" placeholder="All categories" aria-label="Category" />
            <x-ui.select name="status" :options="$statuses" :value="$filters['status'] ?? ''" placeholder="Any status" aria-label="Status" />
            <x-ui.select name="location" :options="$locations" :value="$filters['location'] ?? ''" placeholder="Any location" aria-label="Location" />
            <x-ui.select name="condition" :options="$conditions" :value="$filters['condition'] ?? ''" placeholder="Any condition" aria-label="Condition" />
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-6">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                <label class="flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" name="archived" value="1" @checked($filters['archived'] ?? false) class="size-4 rounded border-ink-300 text-brand-600">Archived only</label>
                @if (array_filter($filters))<a href="{{ route('app.inventory.assets.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Clear filters</a>@endif
                <span class="ml-auto text-sm text-ink-500">{{ number_format($assets->total()) }} {{ Str::plural('unit', $assets->total()) }}</span>
            </div>
        </form>

        @if ($assets->isEmpty())
            <x-ui.empty-state icon="qr-code" title="No assets found" description="Try a different search or clear the filters." />
        @else
            <x-ui.table>
                <x-slot:head><th>Asset tag</th><th>Equipment</th><th>Status</th><th class="hidden md:table-cell">Condition</th><th class="hidden md:table-cell">Location</th><th class="hidden lg:table-cell">Serial</th></x-slot:head>
                @foreach ($assets as $asset)
                    <tr>
                        <td><a href="{{ route('app.inventory.assets.show', $asset) }}" class="font-mono font-semibold text-ink-900 hover:text-brand-700">{{ $asset->asset_tag }}</a></td>
                        <td class="sm:min-w-48"><a href="{{ route('app.inventory.equipment.show', $asset->equipment) }}" class="text-ink-800 hover:text-brand-700">{{ $asset->equipment->name }}</a><span class="block text-xs text-ink-500">{{ $asset->equipment->category?->name }}</span></td>
                        <td><x-ui.badge :tone="$asset->status->tone">{{ $asset->status->label }}</x-ui.badge></td>
                        <td class="hidden whitespace-nowrap md:table-cell">{{ $asset->conditionLabel() }}</td>
                        <td class="hidden whitespace-nowrap text-ink-600 md:table-cell">{{ $asset->location?->name ?? '—' }}</td>
                        <td class="hidden font-mono text-xs text-ink-500 lg:table-cell">{{ $asset->serial_number ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
            @if ($assets->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $assets->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
