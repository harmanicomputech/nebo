@php
    $tz = config('nebo.display_timezone');
    $local = fn ($v) => $v ? \Carbon\Carbon::parse($v)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
@endphp
<x-layouts.app title="Log a maintenance job">
    <x-ui.page-header title="Log a maintenance job" description="Report a fault or plan a service for one unit." :breadcrumbs="['Inventory' => null, 'Maintenance' => route('app.maintenance.index'), 'New job' => null]" />

    <form method="POST" action="{{ route('app.maintenance.store') }}" class="mx-auto max-w-3xl space-y-6" data-once>
        @csrf
        <x-ui.card title="What needs attention">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Asset tag" name="asset_tag" :value="$asset?->asset_tag" required :hint="$asset ? $asset->equipment->name : 'As printed on the label, e.g. ML-004.'" />
                <x-ui.select label="Type" name="type" :options="$types" :value="old('type', 'repair')" required />
                <x-ui.input label="Issue" name="issue" required class="sm:col-span-2" placeholder="e.g. Pan motor grinding, no DMX response" />
                <x-ui.textarea label="Details" name="description" rows="4" class="sm:col-span-2" />
                <x-ui.select label="Priority" name="priority" :options="$priorities" :value="old('priority', 'normal')" required />
                <x-ui.select label="Technician" name="technician_id" :options="$technicians" placeholder="Not assigned yet" />
                <label class="flex items-start gap-3 rounded-xl border border-ink-100 bg-ink-50 p-3 text-sm sm:col-span-2">
                    <input type="hidden" name="out_of_service" value="0">
                    <input type="checkbox" name="out_of_service" value="1" @checked(old('out_of_service', '1') === '1') class="mt-0.5 size-4 rounded border-ink-300 text-brand-600">
                    <span><span class="font-semibold">Take it out of service now</span><span class="block text-ink-500">Marks the unit Maintenance Required so it can't be allocated. Leave unticked for planned servicing of a working unit; a scheduled window below still blocks those dates.</span></span>
                </label>
            </div>
        </x-ui.card>

        <x-ui.card title="Schedule (optional)" description="Lagos time. The unit can't be allocated to events during this window.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Starts" name="scheduled_starts_at" type="datetime-local" :value="$local(old('scheduled_starts_at'))" :use-old="false" />
                <x-ui.input label="Ends" name="scheduled_ends_at" type="datetime-local" :value="$local(old('scheduled_ends_at'))" :use-old="false" />
            </div>
        </x-ui.card>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="url()->previous() !== url()->current() ? url()->previous() : route('app.maintenance.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="wrench">Log job</x-ui.button>
        </div>
    </form>
</x-layouts.app>
