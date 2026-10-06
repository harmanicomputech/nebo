@php
    use App\Support\Format;
    $editing = $event->exists;
    $tz = config('nebo.display_timezone');
    $local = fn ($v) => $v ? \Carbon\Carbon::parse($v)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $title = $editing ? 'Edit '.$event->name : ($fromRequest ? 'Create event from '.$fromRequest->reference : 'New event');
    $action = $editing ? route('app.events.update', $event) : ($fromRequest ? route('app.requests.event.store', $fromRequest) : route('app.events.store'));
    $selected = array_map('intval', old('services', $selectedServices));
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title" description="The hold window runs from setup start to breakdown end. Equipment and crew are booked against it."
        :breadcrumbs="['Operations' => null, 'Events' => route('app.events.index'), ($editing ? $event->reference : 'New') => null]" />

    <form method="POST" action="{{ $action }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3" data-once>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="Event">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Event name" name="name" :value="$event->name" required class="sm:col-span-2" />
                    @if (! $editing && ! $fromRequest)
                        <x-ui.select label="Client" name="customer_id" :options="$customers" placeholder="Choose a client" required class="sm:col-span-2" hint="Clients come from requests or the Customers page." />
                    @elseif ($fromRequest)
                        <div class="sm:col-span-2 rounded-xl bg-ink-50 px-4 py-3 text-sm">Client: <strong>{{ $fromRequest->customer->displayName() }}</strong> · from request <a class="font-mono text-brand-700" href="{{ route('app.requests.show', $fromRequest) }}">{{ $fromRequest->reference }}</a></div>
                    @endif
                    <x-ui.select label="Event type" name="event_type" :options="$types" :value="$event->event_type" required />
                    <x-ui.input label="Venue & location" name="venue" :value="$event->venue" required />
                </div>
            </x-ui.card>
            <x-ui.card title="Schedule" description="Times are Lagos time.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Setup starts" name="setup_starts_at" type="datetime-local" :value="$local(old('setup_starts_at', $event->setup_starts_at))" :use-old="false" required />
                    <x-ui.input label="Event starts" name="starts_at" type="datetime-local" :value="$local(old('starts_at', $event->starts_at))" :use-old="false" required />
                    <x-ui.input label="Event ends" name="ends_at" type="datetime-local" :value="$local(old('ends_at', $event->ends_at))" :use-old="false" required />
                    <x-ui.input label="Breakdown finishes" name="breakdown_ends_at" type="datetime-local" :value="$local(old('breakdown_ends_at', $event->breakdown_ends_at))" :use-old="false" required />
                </div>
            </x-ui.card>
            <x-ui.card title="Services">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $service)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-ink-200 px-3 py-2 text-sm has-checked:border-brand-600 has-checked:bg-brand-50/40">
                            <input type="checkbox" name="services[]" value="{{ $service->id }}" @checked(in_array($service->id, $selected, true)) class="size-4 rounded border-ink-300 text-brand-600">{{ $service->name }}
                        </label>
                    @endforeach
                </div>
            </x-ui.card>
            <x-ui.card title="Production requirements">
                <x-ui.textarea name="production_requirements" :value="$event->production_requirements" rows="6" aria-label="Production requirements" />
            </x-ui.card>
        </div>
        <div class="space-y-6">
            <x-ui.card title="People">
                <div class="space-y-4">
                    <x-ui.select label="Project manager" name="project_manager_id" :options="$managers" :value="$event->project_manager_id" placeholder="Not set" hint="A user account; gets status updates." />
                    <x-ui.select label="Production manager" name="production_manager_id" :options="$productionManagers" :value="$event->production_manager_id" placeholder="Not set" hint="From staff & crew." />
                </div>
            </x-ui.card>
            @can('financial.view')
                <x-ui.card title="Budget">
                    <x-ui.input label="Production budget (₦)" name="budget" inputmode="decimal" :value="Format::nairaInput($event->budget_kobo)" />
                </x-ui.card>
            @endcan
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="$editing ? route('app.events.show', $event) : ($fromRequest ? route('app.requests.show', $fromRequest) : route('app.events.index'))">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? 'Save event' : 'Create event' }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
