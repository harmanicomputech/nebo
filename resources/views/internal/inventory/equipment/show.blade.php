@php
    use App\Support\Format;
    $canCosts = auth()->user()->can('inventory.costs');
    $tabs = $equipment->isSerialized() ? ['units' => 'Units', 'history' => 'History'] : ['stock' => 'Stock by location', 'history' => 'History'];
@endphp
<x-layouts.app :title="$equipment->name">
    <x-ui.page-header :title="$equipment->name" :breadcrumbs="['Inventory' => null, 'Equipment' => route('app.inventory.equipment.index'), $equipment->name => null]">
        <x-slot:actions>
            @if ($equipment->trashed())
                <x-ui.badge tone="neutral">Archived</x-ui.badge>
                @can('restore', $equipment)
                    <form method="POST" action="{{ route('app.inventory.equipment.restore', $equipment->id) }}">@csrf<x-ui.button type="submit" variant="secondary" icon="history">Restore</x-ui.button></form>
                @endcan
            @else
                @can('update', $equipment)<x-ui.button variant="secondary" icon="pencil" :href="route('app.inventory.equipment.edit', $equipment)">Edit</x-ui.button>@endcan
                @can('addAssets', $equipment)<x-ui.button icon="plus" :href="route('app.inventory.assets.create', $equipment)">Add units</x-ui.button>@endcan
                @can('adjustStock', $equipment)<x-ui.button icon="package-check" x-data x-on:click="$dispatch('open-drawer', 'stock-action')">Stock action</x-ui.button>@endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card :padding="false" class="self-start overflow-hidden">
            <div class="grid aspect-[4/3] place-items-center bg-gradient-to-br from-ink-50 to-ink-100">
                @if ($equipment->image_path)
                    <img src="{{ route('app.inventory.equipment.image', $equipment) }}" alt="{{ $equipment->name }}" class="size-full object-cover">
                @else
                    <x-ui.icon :name="$equipment->category?->iconName() ?? 'package'" class="size-16 text-ink-300" />
                @endif
            </div>
            <dl class="divide-y divide-ink-100 text-sm">
                @foreach (array_filter([
                    'SKU' => $equipment->sku,
                    'Category' => $equipment->category?->fullName(),
                    'Manufacturer' => $equipment->manufacturer,
                    'Model' => $equipment->model,
                    'Tracking' => $equipment->tracking_mode->label().' · '.$equipment->unitLabel(),
                    'Asset prefix' => $equipment->asset_prefix ? $equipment->asset_prefix.'-###' : null,
                    'Low-stock alert' => $equipment->low_stock_threshold !== null ? 'Below '.$equipment->low_stock_threshold : null,
                    'Replacement value' => $canCosts && $equipment->replacement_value_kobo ? Format::naira($equipment->replacement_value_kobo).' per unit' : null,
                ]) as $label => $value)
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right font-medium text-ink-900 {{ $label === 'SKU' ? 'font-mono' : '' }}">{{ $value }}</dd></div>
                @endforeach
            </dl>
            @if ($equipment->description)<p class="border-t border-ink-100 px-5 py-4 text-sm text-ink-600">{{ $equipment->description }}</p>@endif
        </x-ui.card>

        <div class="space-y-6 lg:col-span-2">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <x-ui.stat label="Available now" :value="number_format($summary->availableUnits())" icon="circle-check" tone="success" />
                <x-ui.stat label="{{ $equipment->isSerialized() ? 'Units in fleet' : 'Total stock' }}" :value="number_format($summary->totalUnits())" icon="boxes" />
                <x-ui.stat label="{{ $equipment->isSerialized() ? 'Not available' : 'Quarantined' }}" :value="number_format($summary->totalUnits() - $summary->availableUnits())" icon="triangle-alert" :tone="$summary->isLowStock() ? 'brand' : 'neutral'" :hint="$summary->isLowStock() ? 'Below the low-stock level' : null" class="col-span-2 sm:col-span-1" />
            </div>

            @if ($statusBreakdown->isNotEmpty())
                <x-ui.card title="Units by status">
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($statusBreakdown as $row)
                            <li><a href="{{ route('app.inventory.equipment.show', [$equipment, 'tab' => 'units', 'status' => $row['status']->id]) }}" class="inline-flex items-center gap-2 rounded-lg border border-ink-200 px-3 py-1.5 text-sm hover:border-ink-300">
                                <x-ui.badge :tone="$row['status']->tone">{{ $row['status']->label }}</x-ui.badge><span class="font-semibold tabular-nums">{{ $row['count'] }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <div>
                <nav class="flex gap-1 border-b border-ink-200" aria-label="Sections">
                    @foreach ($tabs as $key => $label)
                        <a href="{{ route('app.inventory.equipment.show', [$equipment, 'tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                           @class(['-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold', 'border-brand-600 text-ink-900' => $tab === $key, 'border-transparent text-ink-500 hover:text-ink-900' => $tab !== $key])>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="mt-4">
                    @if ($tab === 'units')
                        <x-ui.card :padding="false">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 px-4 py-3 sm:px-6">
                                <form method="GET" class="flex flex-wrap items-center gap-2" x-data x-on:change="$el.requestSubmit()">
                                    <input type="hidden" name="tab" value="units">
                                    <x-ui.select name="status" :options="$statuses->pluck('label', 'id')->all()" :value="request('status')" placeholder="All statuses" aria-label="Status" class="w-48" />
                                    <label class="flex items-center gap-2 text-sm text-ink-600"><input type="checkbox" name="archived" value="1" @checked(request()->boolean('archived')) class="size-4 rounded border-ink-300 text-brand-600">Archived</label>
                                    <noscript><x-ui.button type="submit" size="sm" variant="dark">Filter</x-ui.button></noscript>
                                </form>
                                @if ($assets->total())
                                    <x-ui.button variant="secondary" size="sm" icon="qr-code" :href="route('app.inventory.labels', ['equipment' => $equipment->id])" target="_blank">Print labels</x-ui.button>
                                @endif
                            </div>
                            @if ($assets->isEmpty())
                                <x-ui.empty-state icon="qr-code" title="No units here" description="Add the physical units of this item to track each one.">
                                    @can('addAssets', $equipment)<x-ui.button :href="route('app.inventory.assets.create', $equipment)" icon="plus">Add units</x-ui.button>@endcan
                                </x-ui.empty-state>
                            @else
                                <x-ui.table>
                                    <x-slot:head><th>Asset tag</th><th>Serial</th><th>Status</th><th>Condition</th><th>Location</th><th><span class="sr-only">Open</span></th></x-slot:head>
                                    @foreach ($assets as $asset)
                                        <tr>
                                            <td><a href="{{ route('app.inventory.assets.show', $asset) }}" class="font-mono font-semibold text-ink-900 hover:text-brand-700">{{ $asset->asset_tag }}</a></td>
                                            <td class="font-mono text-xs text-ink-500">{{ $asset->serial_number ?? '—' }}</td>
                                            <td><x-ui.badge :tone="$asset->status->tone">{{ $asset->status->label }}</x-ui.badge></td>
                                            <td class="whitespace-nowrap">{{ $asset->conditionLabel() }}@if ($asset->conditionBlocksAllocation()) <x-ui.icon name="triangle-alert" class="inline size-3.5 text-brand-600" /><span class="sr-only">(blocks allocation)</span>@endif</td>
                                            <td class="whitespace-nowrap text-ink-600">{{ $asset->location?->name ?? '—' }}</td>
                                            <td class="text-right"><x-ui.button variant="ghost" size="sm" :href="route('app.inventory.assets.show', $asset)">Open</x-ui.button></td>
                                        </tr>
                                    @endforeach
                                </x-ui.table>
                                @if ($assets->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $assets->links() }}</div>@endif
                            @endif
                        </x-ui.card>
                    @elseif ($tab === 'stock')
                        <x-ui.card :padding="false">
                            @if ($levels->isEmpty())
                                <x-ui.empty-state icon="warehouse" title="No stock recorded" description="Receive stock to record how many units are held and where.">
                                    @can('adjustStock', $equipment)<x-ui.button icon="package-check" x-data x-on:click="$dispatch('open-drawer', 'stock-action')">Receive stock</x-ui.button>@endcan
                                </x-ui.empty-state>
                            @else
                                <x-ui.table>
                                    <x-slot:head><th>Location</th><th class="text-right">Available</th><th class="text-right">Quarantined</th><th class="text-right">Total</th></x-slot:head>
                                    @foreach ($levels as $row)
                                        <tr>
                                            <td class="font-medium">{{ $row['location']->name }} <span class="font-mono text-xs text-ink-400">{{ $row['location']->code }}</span></td>
                                            <td class="text-right font-semibold tabular-nums">{{ number_format($row['available']) }}</td>
                                            <td class="text-right tabular-nums {{ $row['quarantine'] ? 'text-brand-700' : 'text-ink-400' }}">{{ number_format($row['quarantine']) }}</td>
                                            <td class="text-right tabular-nums text-ink-600">{{ number_format($row['available'] + $row['quarantine']) }}</td>
                                        </tr>
                                    @endforeach
                                </x-ui.table>
                            @endif
                        </x-ui.card>
                    @else
                        <x-ui.card>
                            @if ($history->isEmpty())
                                <x-ui.empty-state icon="history" title="No movements yet" />
                            @else
                                <ol>@foreach ($history as $t)@include('internal.inventory._ledger-entry', ['t' => $t, 'showItem' => $equipment->isSerialized()])@endforeach</ol>
                                @if ($history->hasPages())<div class="mt-6 border-t border-ink-100 pt-4">{{ $history->links() }}</div>@endif
                            @endif
                        </x-ui.card>
                    @endif
                </div>
            </div>

            @can('delete', $equipment)
                <x-ui.card title="Archive equipment" description="Hides this item from the catalogue. Only possible once all its units are retired, lost or archived and its stock is zero. History is kept.">
                    <x-ui.confirm :action="route('app.inventory.equipment.destroy', $equipment)" method="DELETE" icon="archive" title="Archive {{ $equipment->name }}?"
                        message="It will no longer appear in the catalogue or be available for events. You can restore it later." confirm="Archive">Archive</x-ui.confirm>
                </x-ui.card>
            @endcan
        </div>
    </div>

    @can('adjustStock', $equipment)
        <x-ui.drawer name="stock-action" title="Stock action">
            <form method="POST" action="{{ route('app.inventory.equipment.stock', $equipment) }}" class="space-y-5" data-once x-data="{ action: @js(old('action', 'receive')) }">
                @csrf
                <x-ui.select label="What happened?" name="action" x-model="action" :value="old('action', 'receive')" :options="[
                    'receive' => 'Receive stock (new or found)',
                    'transfer' => 'Move to another location',
                    'quarantine' => 'Quarantine (damaged / to inspect)',
                    'release' => 'Release from quarantine',
                    'write_off' => 'Write off (lost / scrapped)',
                    'adjust' => 'Stock count (set the counted quantity)',
                ]" />
                <x-ui.select label="Location" name="location_id" :options="$locations ?? App\Models\Location::options()" placeholder="Choose a location" required hint="Where the stock is (for moves: where it is now)." />
                <div x-show="action === 'transfer'"><x-ui.select label="To location" name="to_location_id" :options="$locations ?? App\Models\Location::options()" placeholder="Choose a destination" x-bind:disabled="action !== 'transfer'" /></div>
                <div x-show="['write_off', 'adjust'].includes(action)"><x-ui.select label="Which stock?" name="bucket" :options="['available' => 'Available', 'quarantine' => 'Quarantined']" x-bind:disabled="!['write_off', 'adjust'].includes(action)" /></div>
                <x-ui.input name="quantity" type="number" min="0" required x-bind:placeholder="action === 'adjust' ? 'Counted quantity' : 'How many?'" label="Quantity ({{ strtolower($equipment->unitLabel()) }})" />
                <label x-show="action === 'receive'" class="flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" name="purchased" value="1" class="size-4 rounded border-ink-300 text-brand-600">This is a new purchase</label>
                <x-ui.textarea label="Note" name="note" rows="2" hint="Required for write-offs and stock counts." />
                <x-ui.button type="submit" class="w-full" icon="check">Record</x-ui.button>
            </form>
        </x-ui.drawer>
        @if ($errors->any() && old('action'))<div x-data x-init="$nextTick(() => $dispatch('open-drawer', 'stock-action'))"></div>@endif
    @endcan
</x-layouts.app>
