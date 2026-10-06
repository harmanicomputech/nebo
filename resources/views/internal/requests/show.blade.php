@php
    use App\Support\Format;
    use App\Enums\RequestStatus;
    $canStatus = auth()->user()->can('changeStatus', $request);
    $canManage = auth()->user()->can('update', $request);
@endphp
<x-layouts.app :title="$request->reference">
    <x-ui.page-header :title="$request->event_name" :breadcrumbs="['Operations' => null, 'Requests' => route('app.requests.index'), $request->reference => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$request->status->tone()" class="!text-sm">{{ $request->status->label() }}</x-ui.badge>
            @if ($request->converted_event_id)
                <x-ui.button variant="secondary" icon="calendar-range" :href="route('app.events.show', $request->converted_event_id)">Open event</x-ui.button>
            @elseif ($request->status->isWon())
                @can('create', App\Models\Event::class)<x-ui.button variant="dark" icon="calendar-range" :href="route('app.requests.event.create', $request)">Create event</x-ui.button>@endcan
            @endif
            @if ($canStatus && $transitions)<x-ui.button icon="history" x-data x-on:click="$dispatch('open-modal', 'request-status')">Change status</x-ui.button>@endif
        </x-slot:actions>
    </x-ui.page-header>
    <p class="-mt-4 mb-6 font-mono text-sm text-ink-500">{{ $request->reference }} · received {{ Format::datetime($request->created_at) }}</p>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="Event">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Event type' => $request->eventTypeLabel(),
                        'Event date' => Format::date($request->event_date).($request->duration_days > 1 ? ' · '.$request->duration_days.' days' : ''),
                        'Venue & location' => $request->venue,
                        'Setup required' => Format::datetime($request->setup_at),
                        'Starts' => $request->starts_at ? Format::datetime($request->starts_at) : null,
                        'Ends' => $request->ends_at ? Format::datetime($request->ends_at) : null,
                        'Budget' => $request->budgetLabel(),
                        'Existing design / plan' => $request->has_existing_design ? 'Yes' : 'No',
                    ] as $label => $value)
                        @if ($value)<div><dt class="text-xs font-semibold tracking-wider text-ink-500 uppercase">{{ $label }}</dt><dd class="mt-0.5 font-medium text-ink-900">{{ $value }}</dd></div>@endif
                    @endforeach
                </dl>
                <div class="mt-5 border-t border-ink-100 pt-5">
                    <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">Services</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($request->services as $service)<x-ui.badge tone="dark" :dot="false">{{ $service->name }}</x-ui.badge>@endforeach
                        @if ($request->services_other)<x-ui.badge :dot="false">Other: {{ $request->services_other }}</x-ui.badge>@endif
                    </div>
                </div>
                <div class="mt-5 border-t border-ink-100 pt-5">
                    <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">Production requirements</p>
                    <p class="mt-2 text-sm whitespace-pre-line text-ink-800">{{ $request->requirements }}</p>
                </div>
                @if ($request->additional_info)
                    <div class="mt-5 border-t border-ink-100 pt-5">
                        <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">Anything else</p>
                        <p class="mt-2 text-sm whitespace-pre-line text-ink-800">{{ $request->additional_info }}</p>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Documents" description="Files from the customer and the team. Private; downloaded only by signed-in staff.">
                @include('internal.partials.documents', ['documents' => $request->documents, 'uploadUrl' => $canManage && auth()->user()->can('documents.manage') ? route('app.documents.store', ['request', $request->id]) : null])
            </x-ui.card>

            <x-ui.card title="Internal notes">
                @include('internal.partials.notes', ['notes' => $request->notes, 'action' => $canManage ? route('app.requests.notes', $request) : null])
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Contact">
                <p class="font-semibold">{{ $request->contact_person }}</p>
                @if ($request->company)<p class="text-sm text-ink-600">{{ $request->company }}</p>@endif
                <div class="mt-3 space-y-1.5 text-sm">
                    <a href="mailto:{{ $request->email }}" class="flex items-center gap-2 text-brand-700 hover:underline"><x-ui.icon name="mail" class="size-4" />{{ $request->email }}</a>
                    <a href="tel:{{ $request->phone }}" class="flex items-center gap-2 text-brand-700 hover:underline"><x-ui.icon name="phone" class="size-4" />{{ $request->phone }}</a>
                </div>
                @if ($request->customer?->needs_review)
                    <p class="mt-3 flex gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900"><x-ui.icon name="triangle-alert" class="size-4 shrink-0" />Matched an existing customer whose details differ. Check before merging.</p>
                @endif
                @if ($otherRequests->isNotEmpty())
                    <div class="mt-4 border-t border-ink-100 pt-3">
                        <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">Other requests from this customer</p>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($otherRequests as $other)<li><a href="{{ route('app.requests.show', $other) }}" class="hover:text-brand-700">{{ $other->event_name }}</a> <span class="text-xs text-ink-500">· {{ $other->status->label() }}</span></li>@endforeach
                        </ul>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Assigned to">
                @if ($canManage)
                    <form method="POST" action="{{ route('app.requests.assign', $request) }}" class="flex gap-2" data-once>
                        @csrf
                        <x-ui.select name="assigned_to" :options="$assignees" :value="$request->assigned_to" placeholder="Unassigned" class="flex-1" aria-label="Assignee" />
                        <x-ui.button type="submit" variant="secondary">Save</x-ui.button>
                    </form>
                @else
                    <p class="text-sm">{{ $request->assignee?->name ?? 'Unassigned' }}</p>
                @endif
            </x-ui.card>

            <x-ui.card title="Timeline">
                @include('internal.partials.timeline', ['changes' => $request->statusChanges, 'labels' => fn ($s) => RequestStatus::tryFrom($s)?->label() ?? $s])
            </x-ui.card>
        </div>
    </div>

    @if ($canStatus && $transitions)
        <x-ui.modal name="request-status" title="Change status">
            <form method="POST" action="{{ route('app.requests.status', $request) }}" class="space-y-4" data-once>
                @csrf
                <x-ui.select label="Move to" name="status" :options="$transitions" required />
                <x-ui.textarea label="Note" name="note" rows="3" hint="Required when cancelling or declining." />
                <x-ui.button type="submit" class="w-full">Update status</x-ui.button>
            </form>
        </x-ui.modal>
        @if ($errors->has('status') || $errors->has('note'))<div x-data x-init="$nextTick(() => $dispatch('open-modal', 'request-status'))"></div>@endif
    @endif
</x-layouts.app>
