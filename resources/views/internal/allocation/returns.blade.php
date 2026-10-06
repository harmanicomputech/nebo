@php use App\Support\Format; $canCheckIn = auth()->user()->can('checkIn', $event); @endphp
<x-layouts.app :title="'Check-in · '.$event->name">
    <x-ui.page-header :title="'Check-in · '.$event->name" description="Record what came back. Missing and damaged equipment is flagged and the inventory team is notified."
        :breadcrumbs="['Operations' => null, 'Events' => route('app.events.index'), $event->reference => route('app.events.show', [$event, 'tab' => 'allocation']), 'Check-in' => null]" />

    @if ($outstanding->isEmpty())
        <x-ui.card><x-ui.empty-state icon="package-check" title="Nothing is out" description="All equipment for this event has been checked in, or nothing was dispatched." /></x-ui.card>
    @elseif ($canCheckIn)
        <form method="POST" action="{{ route('app.events.returns.store', $event) }}" data-once x-data>
            @csrf
            <x-ui.card :padding="false">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ink-100 px-5 py-4 sm:px-6">
                    <p class="text-sm text-ink-600"><strong>{{ $outstanding->count() }}</strong> line(s) out. Untick anything not back yet.</p>
                    <x-ui.select label="Returned to" name="location_id" :options="$locations" :value="$defaultLocation" required class="w-64" />
                </div>
                <ul class="divide-y divide-ink-100">
                    @foreach ($outstanding as $a)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3 sm:px-6" x-data="{ on: true }">
                            <input type="hidden" name="lines[{{ $a->id }}][include]" value="0">
                            <label class="flex min-w-48 flex-1 items-center gap-3">
                                <input type="checkbox" name="lines[{{ $a->id }}][include]" value="1" x-model="on" class="size-4 rounded text-brand-600">
                                <span><span class="font-mono font-semibold">{{ $a->asset?->asset_tag ?? $a->quantity.' ×' }}</span> <span class="text-sm">{{ $a->equipment->name }}</span></span>
                            </label>
                            @if ($a->asset)
                                <select name="lines[{{ $a->id }}][outcome]" class="field w-48 py-1.5" x-bind:disabled="!on" aria-label="Outcome for {{ $a->asset->asset_tag }}">
                                    @foreach ($outcomes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            @else
                                <div class="flex items-center gap-2 text-xs" x-data="{ r: {{ $a->quantity }}, m: 0, d: 0 }">
                                    <label>Returned <input type="number" min="0" name="lines[{{ $a->id }}][returned]" x-model.number="r" class="field w-20 py-1" x-bind:disabled="!on"></label>
                                    <label>Missing <input type="number" min="0" name="lines[{{ $a->id }}][missing]" x-model.number="m" class="field w-20 py-1" x-bind:disabled="!on"></label>
                                    <label>Damaged <input type="number" min="0" name="lines[{{ $a->id }}][damaged]" x-model.number="d" class="field w-20 py-1" x-bind:disabled="!on"></label>
                                    <span :class="r + m + d === {{ $a->quantity }} ? 'text-emerald-700' : 'font-semibold text-brand-700'" x-text="(r + m + d) + ' / {{ $a->quantity }}'"></span>
                                </div>
                            @endif
                            <input type="text" name="lines[{{ $a->id }}][note]" placeholder="Note" class="field w-full py-1.5 sm:w-48" x-bind:disabled="!on" aria-label="Note">
                            @error("lines.{$a->id}")<p class="w-full text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-wrap items-end gap-3 border-t border-ink-100 px-5 py-4 sm:px-6">
                    <x-ui.input label="Check-in notes" name="notes" class="flex-1" />
                    <x-ui.button type="submit" icon="package-check" class="mb-0.5">Check in</x-ui.button>
                </div>
            </x-ui.card>
        </form>
    @else
        <x-ui.card><p class="text-sm text-ink-600">{{ $outstanding->count() }} line(s) are out. You don't have permission to check equipment in.</p></x-ui.card>
    @endif

    @if ($checks->isNotEmpty())
        <h2 class="mt-8 mb-3 text-lg font-semibold">Previous check-ins</h2>
        <div class="space-y-4">
            @foreach ($checks as $check)
                <x-ui.card :title="Format::datetime($check->created_at).' · '.$check->user_name" :description="$check->returned_count.' returned · '.$check->missing_count.' missing · '.$check->damaged_count.' damaged or needing attention'">
                    <ul class="space-y-1 text-sm">
                        @foreach ($check->items as $item)
                            <li class="flex flex-wrap gap-2"><x-ui.badge :tone="['returned' => 'success', 'missing' => 'danger', 'damaged' => 'danger'][$item->outcome->value] ?? 'warning'">{{ $item->outcome->label() }}</x-ui.badge>
                                <span class="font-mono">{{ $item->allocation->asset?->asset_tag ?? $item->quantity.' ×' }}</span> {{ $item->allocation->equipment->name }}@if ($item->note)<span class="text-ink-500">· {{ $item->note }}</span>@endif</li>
                        @endforeach
                    </ul>
                    @if ($check->notes)<p class="mt-3 text-sm text-ink-600">{{ $check->notes }}</p>@endif
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
