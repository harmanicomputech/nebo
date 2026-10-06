{{-- One trip in a list. $t: LogisticsTrip with event, vehicle, driver, items_count, crew_count; $showEvent --}}
@php use App\Support\Format; @endphp
<li><a href="{{ route('app.logistics.trips.show', $t) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-4 hover:bg-ink-50 sm:px-6">
    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink-950 text-white"><x-ui.icon :name="$t->direction->icon()" class="size-5" /></span>
    <span class="min-w-0 flex-1 basis-56">
        <span class="block truncate font-semibold text-ink-900">{{ $t->origin }} → {{ $t->destination }}</span>
        <span class="block truncate text-xs text-ink-500"><span class="font-mono">{{ $t->reference }}</span> · {{ $t->direction->label() }}@if (($showEvent ?? true) && $t->event) · {{ $t->event->name }}@endif</span>
        <span class="mt-0.5 block text-xs text-ink-500">{{ Format::datetime($t->departs_at, 'D j M, g:ia') }} → {{ Format::datetime($t->arrives_at, 'D j M, g:ia') }}
            · {{ $t->vehicle ? $t->vehicle->name.' ('.$t->vehicle->registration.')' : 'No vehicle' }} · {{ $t->driver?->name ?? 'No driver' }} · {{ $t->items_count }} item(s)@if ($t->crew_count) · {{ $t->crew_count }} crew @endif</span>
    </span>
    <span class="flex items-center gap-2">
        @if ($t->status->isActive() && (! $t->vehicle_id || ! $t->driver_id))<x-ui.badge tone="warning">Needs {{ ! $t->vehicle_id ? 'vehicle' : 'driver' }}</x-ui.badge>@endif
        <x-ui.badge :tone="$t->status->tone()">{{ $t->status->label() }}</x-ui.badge>
    </span>
</a></li>
