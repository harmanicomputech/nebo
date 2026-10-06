@php
    use App\Support\Format;
    use App\Enums\EventStatus;
    $u = auth()->user();
@endphp
<x-layouts.app :title="$event->name">
    <x-ui.page-header :title="$event->name" :breadcrumbs="['Operations' => null, 'Events' => route('app.events.index'), $event->reference => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$event->status->tone()" class="!text-sm"><x-ui.icon :name="$event->status->icon()" class="size-3.5" />{{ $event->status->label() }}</x-ui.badge>
            @if ($event->trashed())
                @can('restore', $event)<form method="POST" action="{{ route('app.events.restore', $event->id) }}">@csrf<x-ui.button type="submit" variant="secondary">Restore</x-ui.button></form>@endcan
            @else
                @can('update', $event)<x-ui.button variant="secondary" icon="pencil" :href="route('app.events.edit', $event)">Edit</x-ui.button>@endcan
                @if ($transitions && $u->can('changeStatus', $event))<x-ui.button icon="history" x-data x-on:click="$dispatch('open-modal', 'event-status')">Change status</x-ui.button>@endif
            @endif
        </x-slot:actions>
    </x-ui.page-header>
    <p class="-mt-4 mb-6 text-sm text-ink-500"><span class="font-mono">{{ $event->reference }}</span> · {{ $event->eventTypeLabel() }} · {{ $event->venue }}</p>

    {{-- Schedule strip --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['Setup starts' => [$event->setup_starts_at, 'package-check'], 'Show starts' => [$event->starts_at, 'radio'], 'Show ends' => [$event->ends_at, 'circle-check'], 'Breakdown done' => [$event->breakdown_ends_at, 'truck']] as $label => [$at, $icon])
            <div class="rounded-2xl border border-ink-100 bg-white p-4 shadow-card">
                <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-ink-500 uppercase"><x-ui.icon :name="$icon" class="size-3.5" />{{ $label }}</p>
                <p class="mt-1 font-semibold">{{ Format::datetime($at, 'D j M') }}</p>
                <p class="text-sm text-ink-500">{{ Format::datetime($at, 'g:i a') }}</p>
            </div>
        @endforeach
    </div>

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-ink-200" aria-label="Event workspace">
        @foreach ($tabs as $key => [$label, $phase])
            @if ($phase)
                <span class="shrink-0 cursor-not-allowed px-3 py-2.5 text-sm font-semibold text-ink-300" title="Planned for phase {{ $phase }}">{{ $label }} <span class="text-[10px] tracking-wider uppercase">· Planned</span></span>
            @else
                <a href="{{ route('app.events.show', [$event, 'tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
                   @class(['-mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm font-semibold', 'border-brand-600 text-ink-900' => $tab === $key, 'border-transparent text-ink-500 hover:text-ink-900' => $tab !== $key])>{{ $label }}@if ($key === 'team') <span class="text-ink-400">{{ $event->team->count() }}</span>@endif</a>
            @endif
        @endforeach
    </nav>

    @if ($tab === 'overview')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-ui.card title="Client" >
                <p class="font-semibold">{{ $event->customer?->company ?: $event->customer?->name }}</p>
                @if ($event->customer?->company)<p class="text-sm text-ink-600">{{ $event->customer->name }}</p>@endif
                <div class="mt-3 space-y-1 text-sm">
                    @if ($event->customer?->email)<a href="mailto:{{ $event->customer->email }}" class="flex items-center gap-2 text-brand-700 hover:underline"><x-ui.icon name="mail" class="size-4" />{{ $event->customer->email }}</a>@endif
                    @if ($event->customer?->phone)<a href="tel:{{ $event->customer->phone }}" class="flex items-center gap-2 text-brand-700 hover:underline"><x-ui.icon name="phone" class="size-4" />{{ $event->customer->phone }}</a>@endif
                </div>
                @if ($event->request)<p class="mt-4 border-t border-ink-100 pt-3 text-sm">From request <a href="{{ route('app.requests.show', $event->request) }}" class="font-mono text-brand-700 hover:underline">{{ $event->request->reference }}</a></p>@endif
            </x-ui.card>
            <x-ui.card title="People">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-ink-500">Project manager</dt><dd class="font-medium">{{ $event->projectManager?->name ?? 'Not set' }}</dd></div>
                    <div><dt class="text-ink-500">Production manager</dt><dd class="font-medium">{{ $event->productionManager?->name ?? 'Not set' }}</dd></div>
                    <div><dt class="text-ink-500">Crew</dt><dd class="font-medium">{{ $event->team->count() }} assigned · <a class="text-brand-700 underline" href="{{ route('app.events.show', [$event, 'tab' => 'team']) }}">View team</a></dd></div>
                </dl>
            </x-ui.card>
            <x-ui.card title="Services">
                <div class="flex flex-wrap gap-2">
                    @forelse ($event->services as $service)<x-ui.badge tone="dark" :dot="false">{{ $service->name }}</x-ui.badge>@empty<p class="text-sm text-ink-500">None recorded.</p>@endforelse
                </div>
            </x-ui.card>
        </div>
    @elseif ($tab === 'requirements')
        <x-ui.card title="Production requirements">
            <p class="text-sm whitespace-pre-line text-ink-800">{{ $event->production_requirements ?: 'No requirements recorded yet.' }}</p>
            <p class="mt-4 text-xs text-ink-500">Equipment quantities, shortages and clashes are on the <a href="{{ route('app.events.show', [$event, 'tab' => 'equipment']) }}" class="font-semibold text-brand-700 hover:underline">Equipment</a> tab.</p>
        </x-ui.card>
    @elseif ($tab === 'equipment')
        @php $canReq = $u->can('manageRequirements', $event); $canAlloc = $u->can('allocate', $event); @endphp
        <x-ui.card title="Equipment requirements" description="What this event needs, checked against every other booking for the hold window {{ Format::datetime($event->setup_starts_at, 'j M g:ia') }} → {{ Format::datetime($event->breakdown_ends_at, 'j M g:ia') }}." :padding="false">
            @if ($analysis->isEmpty())
                <x-ui.empty-state icon="layers" title="No equipment requirements yet" description="Add what this production needs to check availability and allocate units." />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($analysis as $row)
                        @php
                            $req = $row['requirement'];
                            $badge = ['allocated' => ['success', 'Fully allocated'], 'partial' => ['info', 'Partly allocated'], 'shortage' => ['danger', 'Shortage'], 'attention' => ['warning', 'Needs attention']][$row['status']];
                        @endphp
                        <li class="px-5 py-4 sm:px-6" x-data="{ open: {{ $row['shortage'] || $row['status'] === 'attention' ? 'true' : 'false' }} }">
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                <div class="min-w-48 flex-1">
                                    <a href="{{ route('app.inventory.equipment.show', $row['equipment']) }}" class="font-semibold hover:text-brand-700">{{ $row['equipment']->name }}</a>
                                    <p class="text-xs text-ink-500">{{ $row['equipment']->category?->name }} · {{ $row['equipment']->tracking_mode->label() }}@if ($req->notes) · {{ $req->notes }}@endif</p>
                                </div>
                                <dl class="grid grid-cols-3 gap-4 text-center text-sm">
                                    <div><dt class="text-[11px] tracking-wider text-ink-500 uppercase">Required</dt><dd class="font-semibold tabular-nums">{{ $row['required'] }}</dd></div>
                                    <div><dt class="text-[11px] tracking-wider text-ink-500 uppercase">Allocated</dt><dd class="font-semibold tabular-nums">{{ $row['allocated'] }}</dd></div>
                                    <div><dt class="text-[11px] tracking-wider text-ink-500 uppercase">Free</dt><dd class="font-semibold tabular-nums">{{ $row['available'] }}</dd></div>
                                </dl>
                                <x-ui.badge :tone="$badge[0]">{{ $badge[1] }}@if ($row['shortage']) · {{ $row['shortage'] }} short @endif</x-ui.badge>
                                <div class="flex gap-1">
                                    @if ($canAlloc && $row['allocated'] < $row['required'] && $row['available'] > 0)
                                        <x-ui.button size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'alloc-{{ $req->id }}')">Allocate</x-ui.button>
                                    @endif
                                    <x-ui.button size="sm" variant="ghost" x-on:click="open = !open" ::aria-expanded="open" icon="chevron-down" aria-label="Details" />
                                    @if ($canReq)
                                        <x-ui.confirm :action="route('app.events.requirements.destroy', [$event, $req])" method="DELETE" size="sm" variant="ghost" icon="trash-2" title="Remove this requirement?" confirm="Remove" aria-label="Remove requirement" />
                                    @endif
                                </div>
                            </div>
                            <div x-show="open" x-cloak class="mt-4 grid gap-4 lg:grid-cols-3">
                                <div class="rounded-xl bg-ink-50 p-4 text-sm">
                                    <p class="mb-2 text-xs font-semibold tracking-wider text-ink-500 uppercase">Allocated to this event</p>
                                    @if ($row['allocations']->isEmpty())
                                        <p class="text-ink-500">Nothing yet.</p>
                                    @else
                                        <ul class="flex flex-wrap gap-1.5">
                                            @foreach ($row['allocations'] as $a)
                                                <li class="inline-flex items-center gap-1 rounded-md bg-white py-0.5 pr-0.5 pl-2 font-mono text-xs ring-1 ring-ink-200">
                                                    {{ $a->asset?->asset_tag ?? $a->quantity.' × '.($a->location?->code ?? '') }}
                                                    @if ($a->state->value === 'checked_out')<span class="rounded bg-amber-100 px-1 font-sans text-[10px] font-semibold text-amber-800">out</span>@endif
                                                    @if ($canAlloc && $a->state->value === 'reserved')
                                                        <x-ui.confirm :action="route('app.events.allocations.destroy', [$event, $a])" method="DELETE" size="sm" variant="ghost" icon="x" class="!p-0.5" title="Release {{ $a->asset?->asset_tag ?? $a->quantity.' × '.$row['equipment']->name }}?" message="It becomes available to other events." confirm="Release" aria-label="Release {{ $a->asset?->asset_tag ?? 'this allocation' }}" />
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                <div class="rounded-xl bg-ink-50 p-4 text-sm">
                                    <p class="mb-2 text-xs font-semibold tracking-wider text-ink-500 uppercase">Booked on overlapping events</p>
                                    @forelse ($row['conflicts'] as $c)
                                        <p class="py-0.5"><a href="{{ route('app.events.show', $c['event']) }}" class="font-medium hover:text-brand-700">{{ $c['event']->name }}</a> <span class="text-ink-500">· {{ $c['quantity'] }} · {{ Format::date($c['event']->setup_starts_at) }}–{{ Format::date($c['event']->breakdown_ends_at) }}</span></p>
                                    @empty
                                        <p class="text-ink-500">No clashes.</p>
                                    @endforelse
                                </div>
                                <div class="rounded-xl p-4 text-sm {{ $row['shortage'] || $row['unserviceable']->isNotEmpty() ? 'bg-brand-50' : 'bg-ink-50' }}">
                                    @if ($row['unserviceable']->isNotEmpty())
                                        <p class="mb-1 font-semibold text-brand-800">Allocated but out of service</p>
                                        <p class="mb-3 text-brand-800">{{ $row['unserviceable']->map(fn ($a) => $a->asset->asset_tag.' ('.$a->asset->status->label.', '.$a->asset->conditionLabel().')')->implode(', ') }}. Release and replace them.</p>
                                    @endif
                                    @if ($row['shortage'])
                                        <p class="mb-2 text-xs font-semibold tracking-wider text-brand-700 uppercase">Alternatives in {{ $row['equipment']->category?->parent?->name ?? $row['equipment']->category?->name }}</p>
                                        @forelse ($row['alternatives'] as $alt)
                                            <p class="py-0.5"><a href="{{ route('app.inventory.equipment.show', $alt['equipment']) }}" class="font-medium hover:text-brand-700">{{ $alt['equipment']->name }}</a> <span class="text-ink-600">· {{ $alt['available'] }} free</span></p>
                                        @empty
                                            <p class="text-ink-600">No alternatives free for these dates. Consider sub-hire or moving dates.</p>
                                        @endforelse
                                    @elseif ($row['unserviceable']->isEmpty())
                                        <p class="text-ink-500">{{ $row['allocated'] >= $row['required'] ? 'All set.' : 'Enough free units to complete this requirement.' }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($canAlloc)
                                <x-ui.modal :name="'alloc-'.$req->id" :title="'Allocate '.$row['equipment']->name" max-width="lg">
                                    <form method="POST" action="{{ route('app.events.allocations.store', $event) }}" class="space-y-4" data-once x-data="{ mode: '{{ $row['equipment']->isSerialized() ? 'auto' : 'bulk' }}' }">
                                        @csrf
                                        <input type="hidden" name="equipment_id" value="{{ $row['equipment']->id }}">
                                        <p class="text-sm text-ink-600">{{ $row['available'] }} free for these dates · {{ max(0, $row['required'] - $row['allocated']) }} still needed.</p>
                                        @if ($row['equipment']->isSerialized())
                                            <div class="flex gap-2 text-sm">
                                                <label class="flex items-center gap-2"><input type="radio" name="mode" value="auto" x-model="mode" class="text-brand-600">Pick for me</label>
                                                <label class="flex items-center gap-2"><input type="radio" name="mode" value="assets" x-model="mode" class="text-brand-600">Choose units</label>
                                            </div>
                                            <div x-show="mode === 'auto'"><x-ui.input label="How many" name="quantity" type="number" min="1" :max="$row['available']" :value="min($row['available'], max(1, $row['required'] - $row['allocated']))" :id="'q-'.$req->id" x-bind:disabled="mode !== 'auto'" /></div>
                                            <fieldset x-show="mode === 'assets'" x-cloak class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-ink-200 p-3" x-bind:disabled="mode !== 'assets'">
                                                <legend class="sr-only">Units</legend>
                                                @foreach ($row['freeAssets'] as $asset)
                                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="assets[]" value="{{ $asset->id }}" class="rounded text-brand-600"><span class="font-mono">{{ $asset->asset_tag }}</span><span class="text-xs text-ink-500">{{ $asset->location?->name }}</span></label>
                                                @endforeach
                                            </fieldset>
                                        @else
                                            <input type="hidden" name="mode" value="bulk">
                                            <x-ui.input label="Quantity" name="quantity" type="number" min="1" :max="$row['available']" :value="min($row['available'], max(1, $row['required'] - $row['allocated']))" :id="'q-'.$req->id" />
                                        @endif
                                        <x-ui.button type="submit" class="w-full">Allocate</x-ui.button>
                                    </form>
                                </x-ui.modal>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($canReq)
                <form method="POST" action="{{ route('app.events.requirements.store', $event) }}" class="flex flex-wrap items-end gap-3 border-t border-ink-100 bg-ink-50/60 px-5 py-4 sm:px-6" data-once>
                    @csrf
                    <x-ui.select label="Add equipment" name="equipment_id" :options="$equipmentOptions" placeholder="Choose equipment" required class="min-w-56 flex-1" />
                    <x-ui.input label="Quantity" name="quantity" type="number" min="1" required class="w-28" />
                    <x-ui.input label="Notes" name="notes" class="min-w-40 flex-1" />
                    <x-ui.button type="submit" icon="plus" class="mb-0.5">Add</x-ui.button>
                </form>
            @endif
        </x-ui.card>
    @elseif ($tab === 'allocation')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-ui.card title="Load-out & returns" class="lg:order-2">
                <div class="space-y-3 text-sm">
                    @if ($loadList)
                        <p class="flex items-center justify-between"><span>Load list <span class="font-mono">{{ $loadList->reference }}</span></span><x-ui.badge :tone="$loadList->status->tone()">{{ $loadList->status->label() }}</x-ui.badge></p>
                        <x-ui.button variant="secondary" class="w-full" icon="clipboard-check" :href="route('app.events.load-list', $event)">Open load list</x-ui.button>
                    @elseif ($u->can('workLoadList', $event))
                        <form method="POST" action="{{ route('app.events.load-list.sync', $event) }}">@csrf<x-ui.button type="submit" class="w-full" icon="clipboard-list">Create load list</x-ui.button></form>
                    @else
                        <p class="text-ink-500">No load list yet.</p>
                    @endif
                    <x-ui.button variant="secondary" class="w-full" icon="package-check" :href="route('app.events.returns', $event)">Check-in{{ $outstanding ? ' ('.$outstanding.' out)' : '' }}</x-ui.button>
                </div>
            </x-ui.card>
            <x-ui.card title="Allocated equipment" :padding="false" class="lg:order-1 lg:col-span-2">
                @php $rows = collect(['reserved', 'checked_out', 'returned'])->flatMap(fn ($s) => $allocations[$s] ?? collect()); @endphp
                @if ($rows->isEmpty())
                    <x-ui.empty-state icon="layers" title="Nothing allocated yet" description="Allocate from the Equipment tab." />
                @else
                    <x-ui.table>
                        <x-slot:head><th>Item</th><th>Unit / qty</th><th>State</th><th class="hidden sm:table-cell">From</th></x-slot:head>
                        @foreach ($rows as $a)
                            <tr>
                                <td>{{ $a->equipment->name }}</td>
                                <td class="font-mono text-sm">@if ($a->asset)<a class="hover:text-brand-700" href="{{ route('app.inventory.assets.show', $a->asset) }}">{{ $a->asset->asset_tag }}</a>@else {{ $a->quantity }} @endif</td>
                                <td><x-ui.badge :tone="$a->state->tone()">{{ $a->state->label() }}@if ($a->return_outcome && $a->return_outcome !== 'returned') · {{ str_replace('_', ' ', $a->return_outcome) }}@endif</x-ui.badge></td>
                                <td class="hidden text-ink-600 sm:table-cell">{{ $a->location?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                @endif
            </x-ui.card>
        </div>
    @elseif ($tab === 'logistics')
        <x-ui.card title="Trips" description="Moving equipment and crew to the venue and back." :padding="false">
            @can('create', App\Models\LogisticsTrip::class)
                @if ($event->status->holdsResources())
                    <x-slot:actions>
                        <x-ui.button size="sm" icon="truck" :href="route('app.logistics.trips.create', ['event' => $event->id, 'direction' => 'outbound'])">Trip to venue</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="undo-2" :href="route('app.logistics.trips.create', ['event' => $event->id, 'direction' => 'return'])">Return trip</x-ui.button>
                    </x-slot:actions>
                @endif
            @endcan
            @if ($trips->isEmpty())
                <x-ui.empty-state icon="truck" title="No trips planned" description="Plan the trip to the venue once equipment is allocated; add the return trip for breakdown." />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($trips as $t)@include('internal.logistics.trips._row', ['t' => $t, 'showEvent' => false])@endforeach
                </ul>
            @endif
        </x-ui.card>
    @elseif ($tab === 'team')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-ui.card title="Team" :padding="false" class="lg:col-span-2">
                @if ($event->team->isEmpty())
                    <x-ui.empty-state icon="users" title="No crew yet" description="Add the people working this event." />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach ($event->team->sortBy('staff.name') as $member)
                            <li class="flex items-center gap-3 px-5 py-3">
                                <span class="grid size-9 place-items-center rounded-full bg-ink-900 text-xs font-bold text-white">{{ Str::of($member->staff->name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode('') }}</span>
                                <span class="min-w-0 flex-1"><a href="{{ route('app.staff.show', $member->staff) }}" class="block font-medium hover:text-brand-700">{{ $member->staff->name }}</a><span class="block text-xs text-ink-500">{{ $member->roleLabel() }}@if ($member->notes) · {{ $member->notes }}@endif</span></span>
                                @if ($member->staff->phone)<a href="tel:{{ $member->staff->phone }}" class="hidden text-sm text-ink-600 sm:block">{{ $member->staff->phone }}</a>@endif
                                @can('manageTeam', $event)
                                    <x-ui.confirm :action="route('app.events.team.destroy', [$event, $member])" method="DELETE" size="sm" variant="ghost" icon="x" title="Remove {{ $member->staff->name }}?" confirm="Remove" aria-label="Remove {{ $member->staff->name }}" />
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
            @can('manageTeam', $event)
                <x-ui.card title="Add to team">
                    <form method="POST" action="{{ route('app.events.team.store', $event) }}" class="space-y-4" data-once x-data="{ conflict: {{ $errors->has('staff_id') && str_contains($errors->first('staff_id'), 'override') ? 'true' : 'false' }} }">
                        @csrf
                        <x-ui.select label="Person" name="staff_id" :options="$staffOptions" placeholder="Choose" required />
                        <x-ui.select label="Role on this event" name="role" :options="$roles" required />
                        <x-ui.input label="Notes" name="notes" />
                        <div x-show="conflict" x-cloak><x-ui.input label="Override reason" name="override_reason" hint="Required to book someone who is already on an overlapping event." /></div>
                        <x-ui.button type="submit" class="w-full" icon="plus">Add</x-ui.button>
                    </form>
                </x-ui.card>
            @endcan
        </div>
    @elseif ($tab === 'documents')
        <x-ui.card title="Documents" description="Contracts, plans, drawings and other files for this event.">
            @include('internal.partials.documents', ['documents' => $event->documents, 'uploadUrl' => $u->can('documents.manage') && $u->can('update', $event) ? route('app.documents.store', ['event', $event->id]) : null])
        </x-ui.card>
    @elseif ($tab === 'timeline')
        <x-ui.card title="Timeline">
            @include('internal.partials.timeline', ['changes' => $event->statusChanges, 'labels' => fn ($s) => EventStatus::tryFrom($s)?->label() ?? $s])
        </x-ui.card>
    @elseif ($tab === 'notes')
        <x-ui.card title="Internal notes">
            @include('internal.partials.notes', ['notes' => $event->notes, 'action' => $u->can('addNote', $event) ? route('app.events.notes', $event) : null])
        </x-ui.card>
    @elseif ($tab === 'financial')
        <x-ui.card title="Financial">
            @can('financial.view')
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-ink-500">Production budget</dt><dd class="text-xl font-semibold">{{ Format::naira($event->budget_kobo) }}</dd></div>
                    <div><dt class="text-ink-500">Customer's stated budget</dt><dd class="font-medium">{{ $event->request?->budgetLabel() ?? '—' }}</dd></div>
                </dl>
                @php $accepted = $quotes?->where('status', App\Enums\QuotationStatus::Accepted)->sum('total_kobo'); @endphp
                @if ($accepted)
                    <p class="mt-4 text-sm">Accepted quotations: <strong>{{ Format::naira($accepted) }}</strong>@if ($event->budget_kobo) · {{ $accepted >= $event->budget_kobo ? 'covers' : 'is below' }} the production budget @endif</p>
                @endif
            @else
                <p class="text-sm text-ink-500">You don't have access to financial information.</p>
            @endcan
        </x-ui.card>
        @if ($quotes !== null)
            <div class="mt-6">@include('internal.commercial.quotations._list', ['quotes' => $quotes, 'newUrl' => $u->can('create', App\Models\Quotation::class) && ! $event->status->isClosed() ? route('app.quotations.create', ['event' => $event->id]) : null])</div>
        @endif
    @endif

    @if (! $event->trashed() && $u->can('delete', $event) && $tab === 'overview')
        <x-ui.card title="Archive event" description="Hides it from lists. History is kept." class="mt-6">
            <x-ui.confirm :action="route('app.events.destroy', $event)" method="DELETE" icon="archive" title="Archive {{ $event->name }}?" confirm="Archive">Archive</x-ui.confirm>
        </x-ui.card>
    @endif

    @if ($transitions && $u->can('changeStatus', $event))
        <x-ui.modal name="event-status" title="Change status">
            <form method="POST" action="{{ route('app.events.status', $event) }}" class="space-y-4" data-once>
                @csrf
                <x-ui.select label="Move to" name="status" :options="$transitions" required />
                <x-ui.textarea label="Note" name="note" rows="3" hint="Required when cancelling or putting on hold." />
                <x-ui.button type="submit" class="w-full">Update status</x-ui.button>
            </form>
        </x-ui.modal>
        @if ($errors->has('status') || $errors->has('note'))<div x-data x-init="$nextTick(() => $dispatch('open-modal', 'event-status'))"></div>@endif
    @endif
</x-layouts.app>
