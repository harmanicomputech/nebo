@php $canCosts = auth()->user()->can('inventory.costs'); @endphp
<x-layouts.app :title="'Add units · '.$equipment->name">
    <x-ui.page-header title="Add units" :description="$equipment->name.' · tags are generated as '.($equipment->asset_prefix ? $equipment->asset_prefix.'-001, '.$equipment->asset_prefix.'-002 …' : 'you enter them')"
        :breadcrumbs="['Inventory' => null, 'Equipment' => route('app.inventory.equipment.index'), $equipment->name => route('app.inventory.equipment.show', $equipment), 'Add units' => null]" />

    <form method="POST" action="{{ route('app.inventory.assets.store', $equipment) }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3" data-once x-data="{ count: {{ (int) old('count', 1) }} }">
        @csrf
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="How many?">
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-ui.input label="Number of units" name="count" type="number" min="1" max="200" :value="1" required x-model.number="count" />
                    <div class="sm:col-span-2" x-show="count === 1">
                        <x-ui.input label="Asset tag" name="asset_tag" :placeholder="$equipment->asset_prefix ? 'Leave blank to generate' : 'e.g. ML-001'" :required="! $equipment->asset_prefix" x-bind:disabled="count !== 1" />
                    </div>
                    <p class="text-sm text-ink-500 sm:col-span-2" x-show="count > 1" x-cloak>Tags are generated in sequence. Add serial numbers and barcodes per unit afterwards.</p>
                    <div x-show="count === 1"><x-ui.input label="Serial number" name="serial_number" x-bind:disabled="count !== 1" /></div>
                    <div x-show="count === 1"><x-ui.input label="Barcode" name="barcode" x-bind:disabled="count !== 1" hint="If the unit already carries one." /></div>
                </div>
            </x-ui.card>
            <x-ui.card title="Starting state">
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-ui.select label="Status" name="status_id" :options="$statuses" :value="$defaultStatus" required />
                    <x-ui.select label="Condition" name="condition" :options="$conditions" value="good" required />
                    <x-ui.select label="Location" name="location_id" :options="$locations" placeholder="Choose" required />
                </div>
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <x-ui.card title="Purchase & warranty">
                <div class="space-y-4">
                    <x-ui.input label="Purchase date" name="purchase_date" type="date" />
                    @if ($canCosts)<x-ui.input label="Purchase cost per unit (₦)" name="purchase_cost" inputmode="decimal" />@endif
                    <x-ui.input label="Supplier" name="supplier" />
                    <x-ui.input label="Warranty expires" name="warranty_expires_on" type="date" />
                    <x-ui.input label="Next maintenance due" name="next_maintenance_due_on" type="date" />
                    <x-ui.textarea label="Notes" name="notes" rows="2" />
                </div>
            </x-ui.card>
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="route('app.inventory.equipment.show', $equipment)">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check"><span x-text="count > 1 ? 'Add ' + count + ' units' : 'Add unit'">Add units</span></x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
