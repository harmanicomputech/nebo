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
                    <div><dt class="text-ink-500">Crew</dt><dd class="font-medium">{{ $event->team->count() }} assigned · <a class="text-brand-700 hover:underline" href="{{ route('app.events.show', [$event, 'tab' => 'team']) }}">View team</a></dd></div>
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
            <p class="mt-4 text-xs text-ink-500">Equipment requirements, shortages and conflicts arrive with the availability engine (Phase 5).</p>
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
                <p class="mt-4 text-xs text-ink-500">Quotations arrive in Phase 8.</p>
            @else
                <p class="text-sm text-ink-500">You don't have access to financial information.</p>
            @endcan
        </x-ui.card>
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
