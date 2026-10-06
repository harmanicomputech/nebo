@php use App\Support\Format; $q = request()->query(); @endphp
<x-layouts.app title="Equipment">
    <x-ui.page-header title="Equipment" description="The catalogue: every type of equipment Nebo Stage owns, with live availability."
        :breadcrumbs="['Inventory' => null, 'Equipment' => null]">
        <x-slot:actions>
            @can('create', App\Models\Equipment::class)
                <x-ui.button :href="route('app.inventory.equipment.create')" icon="plus">Add equipment</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="border-b border-ink-100 p-4 sm:px-6" x-data="{ more: {{ collect($filters)->except(['q', 'category', 'view', 'sort'])->filter()->isNotEmpty() ? 'true' : 'false' }} }">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="relative flex-1">
                    <label for="q" class="sr-only">Search equipment</label>
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                    <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Name, SKU, make, model, asset tag or serial" class="field pl-9">
                </div>
                <x-ui.select name="category" :options="$categories" :value="$filters['category'] ?? ''" placeholder="All categories" class="lg:w-56" aria-label="Category" />
                <x-ui.select name="sort" :options="['name' => 'Sort: Name', 'sku' => 'Sort: SKU', 'available' => 'Sort: Most available', 'updated' => 'Sort: Recently updated']" :value="$filters['sort'] ?? 'name'" class="lg:w-52" aria-label="Sort" />
                <div class="flex gap-2">
                    <x-ui.button variant="secondary" icon="sliders-horizontal" x-on:click="more = !more" ::aria-expanded="more">Filters</x-ui.button>
                    <x-ui.button type="submit" variant="dark">Apply</x-ui.button>
                </div>
            </div>
            <div x-cloak x-show="more" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <x-ui.select name="mode" :options="['serialized' => 'Serialized', 'bulk' => 'Quantity']" :value="$filters['mode'] ?? ''" placeholder="Any tracking" aria-label="Tracking" />
                <x-ui.select name="availability" :options="['available' => 'Has units available', 'none' => 'None available', 'low' => 'Low stock']" :value="$filters['availability'] ?? ''" placeholder="Any availability" aria-label="Availability" />
                <x-ui.select name="status" :options="$statuses" :value="$filters['status'] ?? ''" placeholder="Any unit status" aria-label="Status" />
                <x-ui.select name="location" :options="$locations" :value="$filters['location'] ?? ''" placeholder="Any location" aria-label="Location" />
                <x-ui.select name="condition" :options="$conditions" :value="$filters['condition'] ?? ''" placeholder="Any condition" aria-label="Condition" />
                <x-ui.select name="manufacturer" :options="$manufacturers" :value="$filters['manufacturer'] ?? ''" placeholder="Any manufacturer" aria-label="Manufacturer" />
                <label class="flex items-center gap-2 text-sm text-ink-700 sm:col-span-2"><input type="checkbox" name="archived" value="1" @checked($filters['archived'] ?? false) class="size-4 rounded border-ink-300 text-brand-600">Show archived only</label>
            </div>
        </form>

        <div class="flex items-center justify-between gap-3 border-b border-ink-100 px-4 py-2.5 sm:px-6">
            <p class="text-sm text-ink-500">{{ number_format($equipment->total()) }} {{ Str::plural('item', $equipment->total()) }}
                @if (collect($filters)->except(['view', 'sort'])->filter()->isNotEmpty())· <a href="{{ route('app.inventory.equipment.index', ['view' => $view]) }}" class="font-semibold text-brand-700 hover:underline">Clear filters</a>@endif
            </p>
            <div class="inline-flex rounded-lg border border-ink-200 p-0.5" role="group" aria-label="View">
                <a href="{{ route('app.inventory.equipment.index', array_merge($q, ['view' => 'table'])) }}" @class(['rounded-md p-1.5', 'bg-ink-900 text-white' => $view === 'table', 'text-ink-500 hover:text-ink-900' => $view !== 'table']) aria-label="Table view" @if ($view === 'table') aria-current="true" @endif><x-ui.icon name="menu" class="size-4" /></a>
                <a href="{{ route('app.inventory.equipment.index', array_merge($q, ['view' => 'grid'])) }}" @class(['rounded-md p-1.5', 'bg-ink-900 text-white' => $view === 'grid', 'text-ink-500 hover:text-ink-900' => $view !== 'grid']) aria-label="Grid view" @if ($view === 'grid') aria-current="true" @endif><x-ui.icon name="layout-dashboard" class="size-4" /></a>
            </div>
        </div>

        @if ($equipment->isEmpty())
            <x-ui.empty-state icon="boxes" title="No equipment found" description="Try a different search or clear the filters.">
                @can('create', App\Models\Equipment::class)<x-ui.button :href="route('app.inventory.equipment.create')" icon="plus">Add equipment</x-ui.button>@endcan
            </x-ui.empty-state>
        @elseif ($view === 'grid')
            <ul class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6 xl:grid-cols-4">
                @foreach ($equipment as $item)
                    <li>
                        <a href="{{ route('app.inventory.equipment.show', $item) }}" class="group flex h-full flex-col overflow-hidden rounded-2xl border border-ink-100 bg-white transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lift">
                            <div class="relative grid aspect-[4/3] place-items-center bg-gradient-to-br from-ink-50 to-ink-100">
                                @if ($item->image_path)
                                    <img src="{{ route('app.inventory.equipment.image', $item) }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover">
                                @else
                                    <x-ui.icon :name="$item->category?->iconName() ?? 'package'" class="size-12 text-ink-300" />
                                @endif
                                <span class="absolute top-3 left-3"><x-ui.badge :tone="$item->isSerialized() ? 'dark' : 'neutral'" :dot="false">{{ $item->tracking_mode->label() }}</x-ui.badge></span>
                            </div>
                            <div class="flex flex-1 flex-col p-4">
                                <p class="text-xs font-medium text-ink-500">{{ $item->category?->fullName() }}</p>
                                <h2 class="mt-0.5 font-semibold text-ink-900 group-hover:text-brand-700">{{ $item->name }}</h2>
                                <p class="font-mono text-xs text-ink-400">{{ $item->sku }}</p>
                                <div class="mt-auto pt-4">
                                    @include('internal.inventory.equipment._availability', ['item' => $item])
                                </div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <x-ui.table>
                <x-slot:head><th>Equipment</th><th class="hidden md:table-cell">Category</th><th class="hidden lg:table-cell">Tracking</th><th class="min-w-36 sm:min-w-48">Available now</th><th class="hidden sm:table-cell"><span class="sr-only">Open</span></th></x-slot:head>
                @foreach ($equipment as $item)
                    <tr>
                        <td>
                            <a href="{{ route('app.inventory.equipment.show', $item) }}" class="flex items-center gap-3">
                                <span class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-ink-50 ring-1 ring-ink-100">
                                    @if ($item->image_path)<img src="{{ route('app.inventory.equipment.image', $item) }}" alt="" loading="lazy" class="size-full object-cover">@else<x-ui.icon :name="$item->category?->iconName() ?? 'package'" class="size-5 text-ink-400" />@endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-semibold text-ink-900 hover:text-brand-700">{{ $item->name }}</span>
                                    <span class="block text-xs text-ink-500"><span class="font-mono">{{ $item->sku }}</span>@if ($item->manufacturer)<span class="hidden sm:inline"> · {{ $item->manufacturer }}</span>@endif</span>
                                </span>
                            </a>
                        </td>
                        <td class="hidden whitespace-nowrap text-ink-600 md:table-cell">{{ $item->category?->fullName() }}</td>
                        <td class="hidden lg:table-cell"><x-ui.badge :tone="$item->isSerialized() ? 'dark' : 'neutral'" :dot="false">{{ $item->tracking_mode->label() }}</x-ui.badge></td>
                        <td>@include('internal.inventory.equipment._availability', ['item' => $item])</td>
                        <td class="hidden text-right sm:table-cell"><x-ui.button variant="secondary" size="sm" :href="route('app.inventory.equipment.show', $item)">Open</x-ui.button></td>
                    </tr>
                @endforeach
            </x-ui.table>
        @endif

        @if ($equipment->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $equipment->links() }}</div>@endif
    </x-ui.card>
</x-layouts.app>
