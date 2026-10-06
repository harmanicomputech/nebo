@php
    use App\Enums\MaintenanceStatus;
    use App\Support\Format;
    $u = auth()->user();
    $canUpdate = $u->can('update', $record);
    $asset = $record->asset;
    $s = $record->status;
    $tz = config('nebo.display_timezone');
    $local = fn ($v) => $v ? \Carbon\Carbon::parse($v)->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $workable = $asset->status->is_manual;
@endphp
<x-layouts.app :title="$record->reference">
    <x-ui.page-header :title="$asset->asset_tag.' · '.$record->issue" :description="$record->reference.' · '.$record->equipment->name"
        :breadcrumbs="['Inventory' => null, 'Maintenance' => route('app.maintenance.index'), $record->reference => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$record->priority->tone()">{{ $record->priority->label() }}</x-ui.badge>
            <x-ui.badge :tone="$s->tone()" class="!text-sm">{{ $s->label() }}</x-ui.badge>
            @if ($canUpdate)
                @if ($s->canMoveTo(MaintenanceStatus::Scheduled))<x-ui.button variant="secondary" icon="calendar-clock" x-data x-on:click="$dispatch('open-modal', 'job-schedule')">{{ $s === MaintenanceStatus::Scheduled ? 'Reschedule' : 'Schedule' }}</x-ui.button>@endif
                @if ($s->canMoveTo(MaintenanceStatus::InProgress) && $workable)
                    <form method="POST" action="{{ route('app.maintenance.start', $record) }}" data-once>@csrf<x-ui.button type="submit" variant="secondary" icon="play">Start work</x-ui.button></form>
                @endif
                @if ($s->canMoveTo(MaintenanceStatus::Completed) && $workable)<x-ui.button icon="circle-check" x-data x-on:click="$dispatch('open-modal', 'job-complete')">Complete</x-ui.button>@endif
                @if ($s->canMoveTo(MaintenanceStatus::Cancelled))<x-ui.button variant="ghost" icon="ban" x-data x-on:click="$dispatch('open-modal', 'job-cancel')">Cancel job</x-ui.button>@endif
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($s->isOpen() && ! $workable)
        <div class="mb-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
            <x-ui.icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
            <p><strong>{{ $asset->asset_tag }}</strong> is {{ $asset->status->label }}@if ($bookings->isNotEmpty()) for {{ $bookings->first()->event->name }}@endif. Work can start once it is checked in, or released or swapped on the event.</p>
        </div>
    @elseif ($s->isOpen() && $bookings->isNotEmpty())
        <div class="mb-6 flex gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="note">
            <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
            <p>{{ $asset->asset_tag }} is booked on {{ $bookings->map(fn ($b) => $b->event->name.' ('.Format::date($b->hold_starts_at).')')->implode(', ') }}. Schedule the work around those dates.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card title="Job" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    @foreach (array_filter([
                        'Asset' => null,
                        'Type' => $record->typeLabel(),
                        'Raised from' => ['manual' => 'Reported', 'inspection' => 'Inspection', 'return' => 'Check-in', 'schedule' => 'Schedule'][$record->source] ?? $record->source,
                        'Reported by' => ($record->reporter?->name ?? '—').' · '.Format::datetime($record->created_at, 'j M Y, g:ia'),
                        'Technician' => $record->technician?->name ?? 'Not assigned',
                        'Window' => $record->scheduled_starts_at ? Format::datetime($record->scheduled_starts_at, 'D j M, g:ia').' → '.Format::datetime($record->scheduled_ends_at, 'D j M, g:ia') : null,
                        'Started' => $record->started_at ? Format::datetime($record->started_at) : null,
                        'Completed' => $record->completed_at ? Format::datetime($record->completed_at) : null,
                        'Condition after' => $record->outcome_condition ? app(App\Support\Lookups::class)->label('condition', $record->outcome_condition) : null,
                        'Cost' => $record->cost_kobo !== null && $u->can('inventory.costs') ? Format::naira($record->cost_kobo) : null,
                    ], fn ($v, $k) => $k === 'Asset' || $v !== null, ARRAY_FILTER_USE_BOTH) as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-3">
                            <dt class="text-ink-500">{{ $label }}</dt>
                            <dd class="text-right font-medium">
                                @if ($label === 'Asset')
                                    <a href="{{ route('app.inventory.assets.show', $asset) }}" class="font-mono text-brand-700 hover:underline">{{ $asset->asset_tag }}</a>
                                    <span class="block text-xs font-normal text-ink-500"><x-ui.badge :tone="$asset->status->tone">{{ $asset->status->label }}</x-ui.badge> · {{ $asset->location?->name }}</span>
                                @else{{ $value }}@endif
                            </dd>
                        </div>
                    @endforeach
                    @if ($record->event)
                        <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Event</dt><dd class="text-right"><a href="{{ route('app.events.show', $record->event) }}" class="font-medium text-brand-700 hover:underline">{{ $record->event->name }}</a></dd></div>
                    @endif
                    @if ($record->schedule)
                        <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Schedule</dt><dd class="text-right font-medium">Every {{ $record->schedule->interval_days }} days</dd></div>
                    @endif
                </dl>
            </x-ui.card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Issue">
                <p class="font-semibold">{{ $record->issue }}</p>
                @if ($record->description)<p class="mt-2 text-sm whitespace-pre-line text-ink-600">{{ $record->description }}</p>@endif
                @if ($record->work_done)
                    <h3 class="mt-5 text-sm font-semibold">Work done</h3>
                    <p class="mt-1 text-sm whitespace-pre-line text-ink-600">{{ $record->work_done }}</p>
                @endif
                @if ($record->parts_used)
                    <h3 class="mt-4 text-sm font-semibold">Parts used</h3>
                    <p class="mt-1 text-sm whitespace-pre-line text-ink-600">{{ $record->parts_used }}</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Photos & documents" description="Damage photos, quotes from suppliers and service reports.">
                @include('internal.partials.documents', ['documents' => $record->documents, 'uploadUrl' => $u->can('documents.manage') && $canUpdate ? route('app.documents.store', ['maintenance', $record->id]) : null])
            </x-ui.card>

            <x-ui.card title="Notes">
                @include('internal.partials.notes', ['notes' => $record->notes, 'action' => $u->can('addNote', $record) ? route('app.maintenance.notes', $record) : null])
            </x-ui.card>

            <x-ui.card title="Timeline">
                @include('internal.partials.timeline', ['changes' => $record->statusChanges, 'labels' => fn ($v) => MaintenanceStatus::tryFrom($v)?->label() ?? $v])
            </x-ui.card>
        </div>
    </div>

    @if ($canUpdate)
        <x-ui.modal name="job-schedule" title="Schedule the work">
            <form method="POST" action="{{ route('app.maintenance.schedule', $record) }}" class="space-y-4" data-once>
                @csrf
                <p class="text-sm text-ink-500">Lagos time. {{ $asset->asset_tag }} can't be allocated during this window.</p>
                <x-ui.input label="Starts" name="scheduled_starts_at" type="datetime-local" :value="$local(old('scheduled_starts_at', $record->scheduled_starts_at))" :use-old="false" required />
                <x-ui.input label="Ends" name="scheduled_ends_at" type="datetime-local" :value="$local(old('scheduled_ends_at', $record->scheduled_ends_at))" :use-old="false" required />
                <x-ui.select label="Technician" name="technician_id" :options="$technicians" :value="$record->technician_id" placeholder="Not assigned" />
                <x-ui.button type="submit" class="w-full">Save schedule</x-ui.button>
            </form>
        </x-ui.modal>
        <x-ui.modal name="job-complete" title="Complete the job" max-width="lg">
            <form method="POST" action="{{ route('app.maintenance.complete', $record) }}" class="space-y-4" data-once>
                @csrf
                <x-ui.textarea label="Work done" name="work_done" rows="3" required />
                <x-ui.select label="Condition now" name="outcome_condition" :options="$conditions" :value="old('outcome_condition', 'good')" required hint="A condition that blocks allocation keeps the unit out of service." />
                <x-ui.textarea label="Parts used" name="parts_used" rows="2" />
                <div class="grid gap-4 sm:grid-cols-2">
                    @can('inventory.costs')<x-ui.input label="Cost (₦)" name="cost" type="number" min="0" step="0.01" />@endcan
                    <x-ui.input label="Next service due" name="next_due_on" type="date" :hint="$record->schedule_id ? 'Leave empty to use the schedule interval.' : null" />
                </div>
                <x-ui.button type="submit" class="w-full" icon="circle-check">Complete job</x-ui.button>
            </form>
        </x-ui.modal>
        <x-ui.modal name="job-cancel" title="Cancel this job?">
            <form method="POST" action="{{ route('app.maintenance.cancel', $record) }}" class="space-y-4" data-once>
                @csrf
                <x-ui.textarea label="Reason" name="note" rows="3" required />
                <x-ui.button type="submit" variant="danger" class="w-full">Cancel job</x-ui.button>
            </form>
        </x-ui.modal>
    @endif
</x-layouts.app>
