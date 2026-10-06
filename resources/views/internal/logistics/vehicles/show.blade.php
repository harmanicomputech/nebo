@php use App\Support\Format; $u = auth()->user(); $papers = $vehicle->expiringPapers(); @endphp
<x-layouts.app :title="$vehicle->name">
    <x-ui.page-header :title="$vehicle->name" :description="$vehicle->registration.' · '.$vehicle->typeLabel()" :breadcrumbs="['Operations' => null, 'Fleet' => route('app.logistics.vehicles.index'), $vehicle->name => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$vehicle->status->tone()" class="!text-sm">{{ $vehicle->status->label() }}</x-ui.badge>
            @if ($vehicle->trashed())
                <x-ui.badge tone="neutral">Archived</x-ui.badge>
                @can('delete', $vehicle)<form method="POST" action="{{ route('app.logistics.vehicles.restore', $vehicle->id) }}">@csrf<x-ui.button type="submit" variant="secondary" icon="history">Restore</x-ui.button></form>@endcan
            @else
                @can('update', $vehicle)<x-ui.button variant="secondary" icon="pencil" :href="route('app.logistics.vehicles.edit', $vehicle)">Edit</x-ui.button>@endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($papers)
        <div class="mb-6 flex gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900" role="note">
            <x-ui.icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
            <p>{{ implode(' and ', $papers) }} {{ count($papers) > 1 ? 'are' : 'is' }} expired or due within 30 days.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card title="Details" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    @foreach (array_filter([
                        'Registration' => $vehicle->registration,
                        'Type' => $vehicle->typeLabel(),
                        'Capacity' => $vehicle->capacity,
                        'Payload' => $vehicle->payload_kg ? number_format($vehicle->payload_kg).' kg' : null,
                        'Regular driver' => $vehicle->defaultDriver?->name,
                        'Based at' => $vehicle->baseLocation?->name,
                        'Insurance until' => $vehicle->insurance_expires_on ? Format::date($vehicle->insurance_expires_on) : null,
                        'Roadworthy until' => $vehicle->roadworthiness_expires_on ? Format::date($vehicle->roadworthiness_expires_on) : null,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right font-medium">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($vehicle->remarks)<p class="border-t border-ink-100 px-5 py-4 text-sm whitespace-pre-line text-ink-600">{{ $vehicle->remarks }}</p>@endif
            </x-ui.card>
            @can('delete', $vehicle)
                @unless ($vehicle->trashed())
                    <x-ui.card title="Archive vehicle" description="Removes it from trip planning. Its trip history is kept.">
                        <x-ui.confirm :action="route('app.logistics.vehicles.destroy', $vehicle)" method="DELETE" icon="archive" title="Archive {{ $vehicle->name }}?" message="It can't be archived while it has upcoming trips." confirm="Archive">Archive</x-ui.confirm>
                    </x-ui.card>
                @endunless
            @endcan
        </div>
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card :title="'Booked trips · '.$upcoming->count()" :padding="false">
                @if ($upcoming->isEmpty())
                    <p class="px-5 py-4 text-sm text-ink-500 sm:px-6">No upcoming trips.</p>
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach ($upcoming as $t)
                            <li><a href="{{ route('app.logistics.trips.show', $t) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                                <span class="min-w-0 flex-1"><span class="block font-medium">{{ $t->origin }} → {{ $t->destination }}</span><span class="block text-xs text-ink-500"><span class="font-mono">{{ $t->reference }}</span> · {{ Format::datetime($t->departs_at, 'D j M, g:ia') }}@if ($t->event) · {{ $t->event->name }}@endif · {{ $t->driver?->name ?? 'No driver' }}</span></span>
                                <x-ui.badge :tone="$t->status->tone()">{{ $t->status->label() }}</x-ui.badge>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
            @if ($past->isNotEmpty())
                <x-ui.card title="Recent trips" :padding="false">
                    <ul class="divide-y divide-ink-100">
                        @foreach ($past as $t)
                            <li><a href="{{ route('app.logistics.trips.show', $t) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                                <span class="min-w-0 flex-1"><span class="block font-medium">{{ $t->origin }} → {{ $t->destination }}</span><span class="block text-xs text-ink-500"><span class="font-mono">{{ $t->reference }}</span> · {{ Format::date($t->departs_at) }}@if ($t->event) · {{ $t->event->name }}@endif</span></span>
                                <x-ui.badge :tone="$t->status->tone()">{{ $t->status->label() }}</x-ui.badge>
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
            <x-ui.card title="Documents" description="Insurance, roadworthiness and service papers.">
                @include('internal.partials.documents', ['documents' => $vehicle->documents, 'uploadUrl' => $u->can('documents.manage') && $u->can('update', $vehicle) ? route('app.documents.store', ['vehicle', $vehicle->id]) : null])
            </x-ui.card>
            <x-ui.card title="Notes">
                @include('internal.partials.notes', ['notes' => $vehicle->notes, 'action' => $u->can('update', $vehicle) ? route('app.logistics.vehicles.notes', $vehicle) : null])
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
