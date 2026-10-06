@php use App\Support\Format; $canCosts = auth()->user()->can('inventory.costs'); @endphp
<x-layouts.app :title="'Edit '.$asset->asset_tag">
    <x-ui.page-header :title="'Edit '.$asset->asset_tag" description="Identity, purchase and warranty details. Change status, location and condition from the asset page so they're recorded in the stock history."
        :breadcrumbs="['Inventory' => null, 'Assets' => route('app.inventory.assets.index'), $asset->asset_tag => route('app.inventory.assets.show', $asset), 'Edit' => null]" />

    <form method="POST" action="{{ route('app.inventory.assets.update', $asset) }}" class="grid grid-cols-1 gap-6 xl:grid-cols-2" data-once>
        @csrf @method('PUT')
        <x-ui.card title="Identity">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Asset tag" name="asset_tag" :value="$asset->asset_tag" required />
                <x-ui.input label="Serial number" name="serial_number" :value="$asset->serial_number" />
                <x-ui.input label="Barcode" name="barcode" :value="$asset->barcode" />
                <x-ui.input label="Next maintenance due" name="next_maintenance_due_on" type="date" :value="$asset->next_maintenance_due_on?->toDateString()" />
                <x-ui.textarea label="Notes" name="notes" :value="$asset->notes" rows="3" class="sm:col-span-2" />
            </div>
        </x-ui.card>
        <x-ui.card title="Purchase & warranty">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Purchase date" name="purchase_date" type="date" :value="$asset->purchase_date?->toDateString()" />
                <x-ui.input label="Supplier" name="supplier" :value="$asset->supplier" />
                @if ($canCosts)
                    <x-ui.input label="Purchase cost (₦)" name="purchase_cost" inputmode="decimal" :value="Format::nairaInput($asset->purchase_cost_kobo)" />
                    <x-ui.input label="Current estimated value (₦)" name="current_value" inputmode="decimal" :value="Format::nairaInput($asset->current_value_kobo)" />
                @endif
                <x-ui.input label="Warranty expires" name="warranty_expires_on" type="date" :value="$asset->warranty_expires_on?->toDateString()" />
            </div>
        </x-ui.card>
        <div class="flex justify-end gap-2 xl:col-span-2">
            <x-ui.button variant="secondary" :href="route('app.inventory.assets.show', $asset)">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save changes</x-ui.button>
        </div>
    </form>
</x-layouts.app>
