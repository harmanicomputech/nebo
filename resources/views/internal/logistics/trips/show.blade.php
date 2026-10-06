@php
    use App\Enums\TripStatus;
    use App\Support\Format;
    $u = auth()->user();
    $s = $trip->status;
    $canProgress = $u->can('progress', $trip);
@endphp
<x-layouts.app :title="$trip->reference">
    <x-ui.page-header :title="$trip->origin.' → '.$trip->destination" :description="$trip->reference.' · '.$trip->direction->label().($trip->event ? ' · '.$trip->event->name : '')"
        :breadcrumbs="['Operations' => null, 'Logistics' => route('app.logistics.index'), $trip->reference => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$s->tone()" class="!text-sm">{{ $s->label() }}</x-ui.badge>
            @can('update', $trip)<x-ui.button variant="secondary" icon="pencil" :href="route('app.logistics.trips.edit', $trip)">Edit</x-ui.button>@endcan
            @if ($canProgress)
                @if ($s->canMoveTo(TripStatus::Loading))
                    <form method="POST" action="{{ route('app.logistics.trips.status', $trip) }}" data-once>@csrf<input type="hidden" name="status" value="loading"><x-ui.button type="submit" variant="secondary" icon="package-check">Start loading</x-ui.button></form>
                @endif
                @if ($s->canMoveTo(TripStatus::InTransit))
                    <form method="POST" action="{{ route('app.logistics.trips.status', $trip) }}" data-once>@csrf<input type="hidden" name="status" value="in_transit"><x-ui.button type="submit" icon="navigation">Depart</x-ui.button></form>
                @endif
                @if ($s->canMoveTo(TripStatus::Arrived))<x-ui.button icon="map-pin" x-data x-on:click="$dispatch('open-modal', 'trip-arrive')">Mark arrived</x-ui.button>@endif
            @endif
            @can('cancel', $trip)
                @if ($s->canMoveTo(TripStatus::Cancelled))<x-ui.button variant="ghost" icon="ban" x-data x-on:click="$dispatch('open-modal', 'trip-cancel')">Cancel trip</x-ui.button>@endif
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($s->isActive() && (! $trip->vehicle_id || ! $trip->driver_id))
        <div class="mb-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
            <x-ui.icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
            <p>This trip has no {{ collect([! $trip->vehicle_id ? 'vehicle' : null, ! $trip->driver_id ? 'driver' : null])->filter()->implode(' or ') }} yet. It can't leave until both are assigned.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card title="Trip" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    @if ($trip->event)
                        <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Event</dt><dd class="text-right">
                            @can('view', $trip->event)<a href="{{ route('app.events.show', [$trip->event, 'tab' => 'logistics']) }}" class="font-medium text-brand-700 hover:underline">{{ $trip->event->name }}</a>@else<span class="font-medium">{{ $trip->event->name }}</span>@endcan
                        </dd></div>
                    @endif
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Departs</dt><dd class="text-right font-medium">{{ Format::datetime($trip->departs_at, 'D j M, g:ia') }}@if ($trip->departed_at)<span class="block text-xs font-normal text-ink-500">Left {{ Format::datetime($trip->departed_at, 'D j M, g:ia') }}</span>@endif</dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Arrives</dt><dd class="text-right font-medium">{{ Format::datetime($trip->arrives_at, 'D j M, g:ia') }}@if ($trip->arrived_at)<span class="block text-xs font-normal text-ink-500">Arrived {{ Format::datetime($trip->arrived_at, 'D j M, g:ia') }}</span>@endif</dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Vehicle</dt><dd class="text-right font-medium">
                        @if ($trip->vehicle)
                            @can('view', $trip->vehicle)<a href="{{ route('app.logistics.vehicles.show', $trip->vehicle) }}" class="text-brand-700 hover:underline">{{ $trip->vehicle->name }}</a>@else{{ $trip->vehicle->name }}@endcan
                            <span class="block font-mono text-xs text-ink-500">{{ $trip->vehicle->registration }}</span>
                        @else — @endif
                    </dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Driver</dt><dd class="text-right font-medium">{{ $trip->driver?->name ?? '—' }}@if ($trip->driver?->phone)<a href="tel:{{ $trip->driver->phone }}" class="block text-xs font-normal text-brand-700">{{ $trip->driver->phone }}</a>@endif</dd></div>
                    @if ($trip->received_by)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Received by</dt><dd class="text-right font-medium">{{ $trip->received_by }}</dd></div>@endif
                </dl>
            </x-ui.card>

            <x-ui.card :title="'Crew · '.$trip->crew->count()" :padding="false">
                @if ($trip->crew->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-500">Only the driver.</p>
                @else
                    <ul class="divide-y divide-ink-100 text-sm">
                        @foreach ($trip->crew as $c)
                            <li class="flex justify-between gap-3 px-5 py-3"><span class="font-medium">{{ $c->name }}<span class="block text-xs font-normal text-ink-500">{{ $c->roleLabel() }}</span></span>@if ($c->phone)<a href="tel:{{ $c->phone }}" class="text-xs text-brand-700">{{ $c->phone }}</a>@endif</li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            @if ($trip->instructions)
                <x-ui.card title="Instructions for the driver"><p class="text-sm whitespace-pre-line text-ink-700">{{ $trip->instructions }}</p></x-ui.card>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="'Manifest · '.$trip->items->count().' line(s)'" :padding="false">
                @if ($trip->items->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-500">No equipment on this trip.</p>
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach ($trip->items->groupBy(fn ($a) => $a->equipment->name) as $name => $group)
                            <li class="px-5 py-3 sm:px-6">
                                <p class="text-sm font-semibold">{{ $name }} <span class="font-normal text-ink-500">· {{ $group->sum('quantity') }}</span></p>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    @foreach ($group->sortBy(fn ($a) => $a->asset?->asset_tag) as $a)
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-ink-50 px-2 py-0.5 text-xs ring-1 ring-ink-100">
                                            <span class="font-mono font-semibold">{{ $a->asset?->asset_tag ?? $a->quantity.' ×' }}</span>
                                            @if ($a->asset)<span class="text-ink-500">{{ $a->asset->status->label }}</span>@else<span class="text-ink-500">{{ $a->state->label() }}</span>@endif
                                        </span>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            <x-ui.card title="Delivery notes & photos" description="Signed delivery notes, waybills and photos.">
                @include('internal.partials.documents', ['documents' => $trip->documents, 'uploadUrl' => $u->can('documents.manage') && $u->can('logistics.manage') ? route('app.documents.store', ['trip', $trip->id]) : null])
            </x-ui.card>

            <x-ui.card title="Notes">
                @include('internal.partials.notes', ['notes' => $trip->notes, 'action' => $u->can('addNote', $trip) ? route('app.logistics.trips.notes', $trip) : null])
            </x-ui.card>

            <x-ui.card title="Timeline">
                @include('internal.partials.timeline', ['changes' => $trip->statusChanges, 'labels' => fn ($v) => TripStatus::tryFrom($v)?->label() ?? $v])
            </x-ui.card>
        </div>
    </div>

    @if ($canProgress && $s->canMoveTo(TripStatus::Arrived))
        <x-ui.modal name="trip-arrive" title="Mark arrived">
            <form method="POST" action="{{ route('app.logistics.trips.status', $trip) }}" class="space-y-4" data-once>
                @csrf
                <input type="hidden" name="status" value="arrived">
                <x-ui.input label="Received by" name="received_by" hint="Name of the person who signed for it." />
                <x-ui.textarea label="Note" name="note" rows="2" />
                <x-ui.button type="submit" class="w-full" icon="map-pin">Mark arrived</x-ui.button>
            </form>
        </x-ui.modal>
    @endif
    @can('cancel', $trip)
        <x-ui.modal name="trip-cancel" title="Cancel this trip?">
            <form method="POST" action="{{ route('app.logistics.trips.status', $trip) }}" class="space-y-4" data-once>
                @csrf
                <input type="hidden" name="status" value="cancelled">
                <x-ui.textarea label="Reason" name="note" rows="3" required />
                <x-ui.button type="submit" variant="danger" class="w-full">Cancel trip</x-ui.button>
            </form>
        </x-ui.modal>
    @endcan
</x-layouts.app>
