@php
    use App\Support\Format;
    use App\Support\QrCode;
    $canCosts = auth()->user()->can('inventory.costs');
    $canChange = auth()->user()->can('changeState', $asset);
    $canInspect = auth()->user()->can('inspect', $asset);
    $canMaintain = auth()->user()->can('maintenance.manage');
    $canSchedule = auth()->user()->can('maintenance.schedule');
    $seesJobs = auth()->user()->can('maintenance.view');
    $managed = ! $asset->status->is_manual;
@endphp
<x-layouts.app :title="$asset->asset_tag">
    <x-ui.page-header :title="$asset->asset_tag" :description="$asset->equipment->name"
        :breadcrumbs="['Inventory' => null, 'Assets' => route('app.inventory.assets.index'), $asset->asset_tag => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$asset->status->tone" class="!text-sm">{{ $asset->status->label }}</x-ui.badge>
            @if ($asset->trashed())
                <x-ui.badge tone="neutral">Archived</x-ui.badge>
                @can('restore', $asset)<form method="POST" action="{{ route('app.inventory.assets.restore', $asset->id) }}">@csrf<x-ui.button type="submit" variant="secondary" icon="history">Restore</x-ui.button></form>@endcan
            @else
                @can('update', $asset)<x-ui.button variant="secondary" icon="pencil" :href="route('app.inventory.assets.edit', $asset)">Edit details</x-ui.button>@endcan
                <x-ui.button variant="secondary" icon="qr-code" :href="route('app.inventory.labels', ['assets' => [$asset->id]])" target="_blank">Print label</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($managed && ! $asset->trashed())
        <div class="mb-6 flex gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="note">
            <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
            <p>This asset is <strong>{{ $asset->status->label }}</strong>. Its status and location are managed by event allocation and returns, so they can't be changed here.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card>
                <div class="flex items-start gap-4">
                    <div class="shrink-0 rounded-lg bg-white p-1 ring-1 ring-ink-100 [&_svg]:size-24">{!! QrCode::svg(route('app.scan', $asset->qr_token), 96) !!}</div>
                    <div class="min-w-0 text-sm">
                        <p class="font-mono text-lg font-semibold">{{ $asset->asset_tag }}</p>
                        <a href="{{ route('app.inventory.equipment.show', $asset->equipment) }}" class="font-medium text-brand-700 hover:underline">{{ $asset->equipment->name }}</a>
                        <p class="text-xs text-ink-500">{{ $asset->equipment->category?->fullName() }}</p>
                        <p class="mt-2 text-xs text-ink-500">Scan the code to open this page.</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Current state" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Status</dt><dd><x-ui.badge :tone="$asset->status->tone">{{ $asset->status->label }}</x-ui.badge></dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Condition</dt><dd class="font-medium">{{ $asset->conditionLabel() }}@if ($asset->conditionBlocksAllocation()) <x-ui.badge tone="danger" class="ml-1">Blocks allocation</x-ui.badge>@endif</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Location</dt><dd class="text-right font-medium">{{ $asset->location?->name ?? '—' }}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Can be allocated</dt><dd>@if ($asset->isAllocatable())<x-ui.badge tone="success">Yes</x-ui.badge>@else<x-ui.badge tone="neutral">No</x-ui.badge>@endif</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Last inspected</dt><dd>{{ Format::datetime($asset->last_inspected_at) }}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Next maintenance</dt><dd @class(['font-semibold text-brand-700' => $asset->next_maintenance_due_on?->isPast()])>{{ Format::date($asset->next_maintenance_due_on) }}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Times deployed</dt><dd class="tabular-nums">{{ $asset->usage_count }}</dd></div>
                </dl>
                @if (($canChange || $canInspect) && ! $asset->trashed())
                    <div class="grid grid-cols-3 gap-2 border-t border-ink-100 p-4">
                        @if ($canInspect)<x-ui.button size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'asset-condition')">Inspect</x-ui.button>@endif
                        @if ($canChange)
                            <x-ui.button size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'asset-status')" :disabled="$managed">Status</x-ui.button>
                            <x-ui.button size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'asset-move')" :disabled="$managed">Move</x-ui.button>
                        @endif
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Identity & purchase" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    @foreach (array_filter([
                        'Serial number' => $asset->serial_number,
                        'Barcode' => $asset->barcode,
                        'Manufacturer' => $asset->equipment->manufacturer,
                        'Model' => $asset->equipment->model,
                        'Purchased' => $asset->purchase_date ? Format::date($asset->purchase_date) : null,
                        'Supplier' => $asset->supplier,
                        'Purchase cost' => $canCosts && $asset->purchase_cost_kobo !== null ? Format::naira($asset->purchase_cost_kobo) : null,
                        'Current value' => $canCosts && $asset->current_value_kobo !== null ? Format::naira($asset->current_value_kobo) : null,
                        'Warranty until' => $asset->warranty_expires_on ? Format::date($asset->warranty_expires_on).($asset->warranty_expires_on->isPast() ? ' (expired)' : '') : null,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right font-medium {{ in_array($label, ['Serial number', 'Barcode']) ? 'font-mono text-xs' : '' }}">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($asset->notes)<p class="border-t border-ink-100 px-5 py-4 text-sm whitespace-pre-line text-ink-600">{{ $asset->notes }}</p>@endif
            </x-ui.card>

            @can('delete', $asset)
                @unless ($managed)
                    <x-ui.card title="Archive asset" description="Removes it from lists. To take it out of service, set the status to Retired instead. History is kept.">
                        <x-ui.confirm :action="route('app.inventory.assets.destroy', $asset)" method="DELETE" icon="archive" title="Archive {{ $asset->asset_tag }}?" message="It will disappear from inventory lists. You can restore it later." confirm="Archive">Archive</x-ui.confirm>
                    </x-ui.card>
                @endunless
            @endcan
        </div>

        <div class="space-y-6 lg:col-span-2">
        @if ($bookings->isNotEmpty())
            <x-ui.card title="Booked on" description="Events holding this unit." :padding="false">
                <ul class="divide-y divide-ink-100">
                    @foreach ($bookings as $b)
                        <li><a href="{{ route('app.events.show', [$b->event, 'tab' => 'allocation']) }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-ink-50">
                            <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $b->event->name }}</span><span class="block text-xs text-ink-500">{{ Format::datetime($b->hold_starts_at, 'D j M, g:ia') }} → {{ Format::datetime($b->hold_ends_at, 'D j M, g:ia') }}</span></span>
                            <x-ui.badge :tone="$b->state->tone()">{{ $b->state->label() }}</x-ui.badge>
                        </a></li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
        @if ($seesJobs || $canMaintain)
            <x-ui.card title="Maintenance" :padding="false">
                <x-slot:actions>
                    @if ($canMaintain && ! $asset->trashed())<x-ui.button size="sm" variant="secondary" icon="wrench" :href="route('app.maintenance.create', ['asset' => $asset->asset_tag])">Log a job</x-ui.button>@endif
                    @if ($canSchedule && ! $asset->trashed())<x-ui.button size="sm" variant="secondary" icon="calendar-clock" x-data x-on:click="$dispatch('open-modal', 'asset-schedule')">Add schedule</x-ui.button>@endif
                </x-slot:actions>
                @if ($jobs->isEmpty() && $schedules->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-500 sm:px-6">No maintenance jobs or schedules yet.</p>
                @endif
                @if ($schedules->isNotEmpty())
                    <h3 class="px-5 pt-4 text-xs font-semibold tracking-wider text-ink-500 uppercase sm:px-6">Schedules</h3>
                    <ul class="divide-y divide-ink-100">
                        @foreach ($schedules as $sc)
                            <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 sm:px-6">
                                <span class="min-w-0 flex-1"><span class="block font-medium">{{ $sc->typeLabel() }} every {{ $sc->interval_days }} days</span><span class="block text-xs text-ink-500">Last done {{ $sc->last_done_on?->format('j M Y') ?? 'never' }}@if ($sc->notes) · {{ $sc->notes }}@endif</span></span>
                                <span @class(['text-sm tabular-nums', 'font-semibold text-brand-700' => $sc->is_active && $sc->isOverdue(), 'text-ink-600' => ! ($sc->is_active && $sc->isOverdue())])>{{ $sc->is_active ? 'Due '.$sc->next_due_on->format('j M Y') : 'Paused' }}</span>
                                @if ($canSchedule)<x-ui.button size="sm" variant="ghost" icon="pencil" x-data x-on:click="$dispatch('open-modal', 'schedule-{{ $sc->id }}')"><span class="sr-only">Edit schedule</span></x-ui.button>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($jobs->isNotEmpty())
                    <h3 class="border-t border-ink-100 px-5 pt-4 text-xs font-semibold tracking-wider text-ink-500 uppercase sm:px-6">Jobs</h3>
                    <ul class="divide-y divide-ink-100">
                        @foreach ($jobs as $job)
                            <li><a href="{{ route('app.maintenance.show', $job) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                                <span class="min-w-0 flex-1"><span class="block font-medium">{{ $job->issue }}</span><span class="block text-xs text-ink-500"><span class="font-mono">{{ $job->reference }}</span> · {{ $job->typeLabel() }} · {{ Format::date($job->created_at) }}</span></span>
                                <x-ui.badge :tone="$job->status->tone()">{{ $job->status->label() }}</x-ui.badge>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endif

        @if ($conditionReports->isNotEmpty())
            <x-ui.card title="Condition history" description="Inspections, check-ins and repairs." :padding="false">
                <ul class="divide-y divide-ink-100">
                    @foreach ($conditionReports as $cr)
                        <li class="px-5 py-3 sm:px-6">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                <x-ui.badge tone="neutral">{{ $cr->sourceLabel() }}</x-ui.badge>
                                <span>{{ $cr->conditionLabel($cr->from_condition) }} → <strong>{{ $cr->conditionLabel($cr->to_condition) }}</strong></span>
                                <span class="text-xs text-ink-500">{{ $cr->user_name }} · {{ Format::datetime($cr->created_at, 'j M Y, g:ia') }}@if ($cr->event) · {{ $cr->event->name }}@endif @if ($cr->maintenanceRecord) · {{ $cr->maintenanceRecord->reference }}@endif</span>
                            </div>
                            @if ($cr->note)<p class="mt-1 text-sm whitespace-pre-line text-ink-600">{{ $cr->note }}</p>@endif
                            @if ($cr->documents->isNotEmpty() && auth()->user()->can('documents.view'))
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($cr->documents as $photo)
                                        <a href="{{ route('app.documents.download', $photo) }}" target="_blank" class="block size-16 overflow-hidden rounded-lg ring-1 ring-ink-100"><img src="{{ route('app.documents.download', $photo) }}" alt="Inspection photo {{ $loop->iteration }}" class="size-full object-cover" loading="lazy"></a>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card title="History" description="Every status, location and condition change.">
            @if ($history->isEmpty())
                <x-ui.empty-state icon="history" title="No history yet" />
            @else
                <ol>@foreach ($history as $t)@include('internal.inventory._ledger-entry', ['t' => $t])@endforeach</ol>
                @if ($history->hasPages())<div class="mt-6 border-t border-ink-100 pt-4">{{ $history->links() }}</div>@endif
            @endif
        </x-ui.card>
        </div>
    </div>

    @if ($canInspect && ! $asset->trashed())
        <x-ui.modal name="asset-condition" title="Inspection / damage report" max-width="lg">
            <form method="POST" action="{{ route('app.inventory.assets.condition', $asset) }}" enctype="multipart/form-data" class="space-y-4" data-once x-data="{ job: {{ old('raise_job') ? 'true' : 'false' }} }">
                @csrf
                <x-ui.select label="Condition" name="condition" :options="$conditions" :value="$asset->condition" required hint="Damaged, critical or needs-inspection conditions take the unit out of service automatically." />
                <x-ui.textarea label="What you found" name="note" rows="3" />
                <div>
                    <label for="f-photos" class="mb-1.5 block text-sm font-medium text-ink-800">Photos</label>
                    <input id="f-photos" type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" capture="environment" multiple class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
                    <p class="mt-1.5 text-xs text-ink-500">Up to 5 JPG, PNG or WebP images.</p>
                    @error('photos')<p class="mt-1.5 text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                    @error('photos.*')<p class="mt-1.5 text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                </div>
                @if ($canMaintain)
                    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" name="raise_job" value="1" x-model="job" class="size-4 rounded border-ink-300 text-brand-600">Open a maintenance job for this</label>
                    <div x-show="job" x-cloak class="space-y-4 rounded-xl border border-ink-100 bg-ink-50 p-4">
                        <x-ui.input label="Issue" name="job_issue" x-bind:required="job" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select label="Type" name="job_type" :options="$maintenanceTypes" :value="old('job_type', 'repair')" />
                            <x-ui.select label="Priority" name="job_priority" :options="$priorities" :value="old('job_priority', 'normal')" />
                        </div>
                    </div>
                @endif
                <x-ui.button type="submit" class="w-full" icon="clipboard-check">Save inspection</x-ui.button>
            </form>
        </x-ui.modal>
    @endif
    @if ($canSchedule && ! $asset->trashed())
        <x-ui.modal name="asset-schedule" title="Add a maintenance schedule">
            <form method="POST" action="{{ route('app.maintenance.schedules.store') }}" class="space-y-4" data-once>
                @csrf
                <x-ui.select label="Type" name="type" :options="$maintenanceTypes" :value="old('type', 'preventive')" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Every (days)" name="interval_days" type="number" min="1" max="3650" :value="old('interval_days', 90)" required />
                    <x-ui.input label="First due" name="next_due_on" type="date" :value="old('next_due_on', now(config('nebo.display_timezone'))->addDays(30)->toDateString())" required />
                </div>
                <x-ui.textarea label="Instructions" name="notes" rows="2" />
                <fieldset class="space-y-2 text-sm">
                    <legend class="mb-1 font-medium text-ink-800">Apply to</legend>
                    <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                    <input type="hidden" name="equipment_id" value="{{ $asset->equipment_id }}">
                    <label class="flex items-center gap-2"><input type="radio" name="scope" value="asset" checked class="text-brand-600"> This unit only ({{ $asset->asset_tag }})</label>
                    <label class="flex items-center gap-2"><input type="radio" name="scope" value="equipment" class="text-brand-600"> Every unit of {{ $asset->equipment->name }}</label>
                </fieldset>
                <x-ui.button type="submit" class="w-full">Add schedule</x-ui.button>
            </form>
        </x-ui.modal>
        @foreach ($schedules as $sc)
            <x-ui.modal :name="'schedule-'.$sc->id" :title="'Edit '.$sc->typeLabel().' schedule'">
                <form method="POST" action="{{ route('app.maintenance.schedules.update', $sc) }}" class="space-y-4" data-once>
                    @csrf @method('PUT')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Every (days)" name="interval_days" type="number" min="1" max="3650" :value="$sc->interval_days" :use-old="false" required :id="'iv-'.$sc->id" />
                        <x-ui.input label="Next due" name="next_due_on" type="date" :value="$sc->next_due_on->toDateString()" :use-old="false" required :id="'nd-'.$sc->id" />
                    </div>
                    <x-ui.textarea label="Instructions" name="notes" rows="2" :value="$sc->notes" :id="'nt-'.$sc->id" />
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($sc->is_active) class="size-4 rounded border-ink-300 text-brand-600"> Active</label>
                    <x-ui.button type="submit" class="w-full">Save</x-ui.button>
                </form>
            </x-ui.modal>
        @endforeach
    @endif
    @if ($canChange && ! $asset->trashed())
        @unless ($managed)
            <x-ui.modal name="asset-status" title="Change status">
                <form method="POST" action="{{ route('app.inventory.assets.status', $asset) }}" class="space-y-4" data-once>
                    @csrf
                    <x-ui.select label="New status" name="status_id" :options="$manualStatuses" :value="$asset->status_id" required hint="Lost and Retired need archive permission." />
                    <x-ui.textarea label="Reason" name="note" rows="3" />
                    <x-ui.button type="submit" class="w-full">Change status</x-ui.button>
                </form>
            </x-ui.modal>
            <x-ui.modal name="asset-move" title="Move to another location">
                <form method="POST" action="{{ route('app.inventory.assets.move', $asset) }}" class="space-y-4" data-once>
                    @csrf
                    <x-ui.select label="Location" name="location_id" :options="$locations" :value="$asset->location_id" required />
                    <x-ui.textarea label="Note" name="note" rows="2" />
                    <x-ui.button type="submit" class="w-full">Move</x-ui.button>
                </form>
            </x-ui.modal>
        @endunless
    @endif
</x-layouts.app>
