@php $editing = $vehicle->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$vehicle->name : 'Add vehicle'">
    <x-ui.page-header :title="$editing ? 'Edit '.$vehicle->name : 'Add vehicle'" :breadcrumbs="['Operations' => null, 'Fleet' => route('app.logistics.vehicles.index'), ($editing ? $vehicle->name : 'New') => null]" />
    <form method="POST" action="{{ $editing ? route('app.logistics.vehicles.update', $vehicle) : route('app.logistics.vehicles.store') }}" class="mx-auto max-w-3xl space-y-6" data-once>
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-ui.card title="Vehicle">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Name" name="name" :value="$vehicle->name" required placeholder="e.g. Truck 2 (10 t)" />
                <x-ui.input label="Registration" name="registration" :value="$vehicle->registration" required placeholder="e.g. LSD 482 XA" />
                <x-ui.select label="Type" name="type" :options="$types" :value="$vehicle->type" required />
                <x-ui.select label="Status" name="status" :options="$statuses" :value="$vehicle->status?->value" required />
                <x-ui.input label="Capacity" name="capacity" :value="$vehicle->capacity" placeholder="e.g. 10 t · 40 m³ · 6 seats" />
                <x-ui.input label="Payload (kg)" name="payload_kg" type="number" min="0" :value="$vehicle->payload_kg" />
                <x-ui.select label="Regular driver" name="default_driver_id" :options="$drivers" :value="$vehicle->default_driver_id" placeholder="None" />
                <x-ui.select label="Based at" name="base_location_id" :options="$locations" :value="$vehicle->base_location_id" placeholder="Not set" />
            </div>
        </x-ui.card>
        <x-ui.card title="Papers" description="Shown as due 30 days before they expire.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Insurance expires" name="insurance_expires_on" type="date" :value="$vehicle->insurance_expires_on?->toDateString()" />
                <x-ui.input label="Roadworthiness expires" name="roadworthiness_expires_on" type="date" :value="$vehicle->roadworthiness_expires_on?->toDateString()" />
                <x-ui.textarea label="Remarks" name="remarks" :value="$vehicle->remarks" rows="3" class="sm:col-span-2" />
            </div>
        </x-ui.card>
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="$editing ? route('app.logistics.vehicles.show', $vehicle) : route('app.logistics.vehicles.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">{{ $editing ? 'Save vehicle' : 'Add vehicle' }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
