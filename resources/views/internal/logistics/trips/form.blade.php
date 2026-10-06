@php
    $editing = $trip->exists;
    $tz = config('nebo.display_timezone');
    $local = fn ($v) => $v ? \Carbon\Carbon::parse($v)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $chosen = array_map('intval', old('items', $selected));
    $crewChosen = array_map('intval', old('crew', $selectedCrew));
    $crumbs = ['Operations' => null, 'Logistics' => route('app.logistics.index')] + ($event ? [$event->name => route('app.events.show', [$event, 'tab' => 'logistics'])] : []) + [($editing ? $trip->reference : 'New trip') => null];
    $title = $editing ? 'Edit '.$trip->reference : ($event ? $trip->direction->label().' · '.$event->name : 'Plan a transfer');
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title" :description="$event ? $event->reference.' · '.$event->venue : 'Move equipment between sites.'"
        :breadcrumbs="$crumbs" />

    <form method="POST" action="{{ $editing ? route('app.logistics.trips.update', $trip) : route('app.logistics.trips.store') }}" class="mx-auto max-w-4xl space-y-6" data-once>
        @csrf
        @if ($editing) @method('PUT') @endif
        @unless ($editing)
            <input type="hidden" name="direction" value="{{ $trip->direction->value }}">
            @if ($event)<input type="hidden" name="event_id" value="{{ $event->id }}">@endif
        @endunless

        <x-ui.card title="Route & times" description="Lagos time. Vehicle and driver clashes are checked against this window.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="From" name="origin" :value="$trip->origin" required />
                <x-ui.input label="To" name="destination" :value="$trip->destination" required />
                <x-ui.input label="Departs" name="departs_at" type="datetime-local" :value="$local(old('departs_at', $trip->departs_at))" :use-old="false" required />
                <x-ui.input label="Arrives" name="arrives_at" type="datetime-local" :value="$local(old('arrives_at', $trip->arrives_at))" :use-old="false" required />
            </div>
        </x-ui.card>

        <x-ui.card title="Vehicle & people">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.select label="Vehicle" name="vehicle_id" :options="$vehicles" :value="$trip->vehicle_id" placeholder="Choose later" hint="Needed before the trip leaves." />
                <x-ui.select label="Driver" name="driver_id" :options="$staff" :value="$trip->driver_id" placeholder="Choose later" hint="Needed before the trip leaves." />
                <fieldset class="sm:col-span-2">
                    <legend class="mb-1.5 text-sm font-medium text-ink-800">Crew travelling</legend>
                    <div class="grid max-h-56 gap-1 overflow-y-auto rounded-xl border border-ink-100 p-3 sm:grid-cols-2">
                        @foreach ($staff as $id => $label)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="crew[]" value="{{ $id }}" @checked(in_array($id, $crewChosen, true)) class="size-4 rounded border-ink-300 text-brand-600"><span class="min-w-0 truncate">{{ $label }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
                @if ($errors->has('override_reason') || old('override_reason'))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 sm:col-span-2">
                        <x-ui.textarea label="Reason for double-booking" name="override_reason" rows="2" hint="Recorded in the audit log." />
                    </div>
                @endif
                <x-ui.textarea label="Instructions for the driver" name="instructions" :value="$trip->instructions" rows="3" class="sm:col-span-2" />
            </div>
        </x-ui.card>

        @if ($event)
            <x-ui.card :title="'Manifest · '.$candidates->count().' available'" :description="$trip->direction->value === 'return' ? 'Equipment out at the event that comes back on this trip.' : 'Equipment booked for the event that goes on this trip. Items must be dispatched from the load list before the trip leaves.'" :padding="false">
                @error('items')<p class="px-5 pt-4 text-sm font-medium text-brand-700 sm:px-6">{{ $message }}</p>@enderror
                @if ($candidates->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-500 sm:px-6">{{ $trip->direction->value === 'return' ? 'Nothing is out at this event yet.' : 'No equipment is allocated to this event yet.' }}</p>
                @else
                    <div x-data class="flex gap-3 border-b border-ink-100 px-5 py-2 text-xs sm:px-6">
                        <button type="button" class="font-semibold text-brand-700" x-on:click="$root.closest('section').querySelectorAll('input[name=\'items[]\']:not(:disabled)').forEach(c => c.checked = true)">Select all</button>
                        <button type="button" class="font-semibold text-ink-500" x-on:click="$root.closest('section').querySelectorAll('input[name=\'items[]\']').forEach(c => c.checked = false)">Clear</button>
                    </div>
                    <ul class="max-h-96 divide-y divide-ink-100 overflow-y-auto">
                        @foreach ($candidates->groupBy(fn ($a) => $a->equipment->name) as $name => $group)
                            <li class="px-5 py-3 sm:px-6">
                                <p class="mb-1.5 text-sm font-semibold">{{ $name }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($group as $a)
                                        @php $elsewhere = $onTrips[$a->id] ?? null; @endphp
                                        <label @class(['inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs', 'border-ink-200' => ! $elsewhere, 'border-ink-100 bg-ink-50 text-ink-400' => $elsewhere])>
                                            <input type="checkbox" name="items[]" value="{{ $a->id }}" @checked(in_array($a->id, $chosen, true)) @disabled($elsewhere) class="size-3.5 rounded border-ink-300 text-brand-600">
                                            <span class="font-mono font-semibold">{{ $a->asset?->asset_tag ?? $a->quantity.' ×' }}</span>
                                            @if ($a->state->value === 'reserved')<span class="text-amber-700">not dispatched</span>@endif
                                            @if ($elsewhere)<span>on {{ $elsewhere }}</span>@endif
                                        </label>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="$editing ? route('app.logistics.trips.show', $trip) : ($event ? route('app.events.show', [$event, 'tab' => 'logistics']) : route('app.logistics.index'))">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">{{ $editing ? 'Save trip' : 'Plan trip' }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
