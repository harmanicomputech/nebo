{{-- One inventory ledger row as a timeline item. Expects $t (InventoryTransaction); $showItem adds the equipment/asset name. --}}
@php
    use App\Support\Format;
    $lookups = app(App\Support\Lookups::class);
    $tone = match ($t->type->value) {
        'written_off', 'quarantined' => 'bg-brand-600',
        'added', 'purchased', 'released', 'restored' => 'bg-emerald-600',
        'condition_changed', 'status_changed' => 'bg-amber-500',
        default => 'bg-ink-800',
    };
    // "From → to" when something changed from a known value; just the result when it was first set.
    $change = fn ($from, $to) => $from === $to || ($from === null && $to === null) ? null : ($from === null ? $to : ($to === null ? $from.' → —' : $from.' → '.$to));
    $details = array_filter([
        $t->from_status_id !== $t->to_status_id ? $change($t->fromStatus?->label, $t->toStatus?->label) : null,
        $t->from_location_id !== $t->to_location_id ? $change($t->fromLocation?->name, $t->toLocation?->name) : null,
        $t->from_condition !== $t->to_condition ? $change($t->from_condition ? $lookups->label('condition', $t->from_condition) : null, $t->to_condition ? $lookups->label('condition', $t->to_condition) : null) : null,
        $t->from_bucket !== $t->to_bucket ? $change($t->from_bucket ? ucfirst($t->from_bucket) : null, $t->to_bucket ? ucfirst($t->to_bucket) : null) : null,
    ]);
@endphp
<li class="relative flex gap-4 pb-6 last:pb-0">
    <span class="absolute top-8 bottom-0 left-4 w-px bg-ink-100" aria-hidden="true"></span>
    <span class="relative grid size-8 shrink-0 place-items-center rounded-full text-white {{ $tone }}"><x-ui.icon :name="$t->type->icon()" class="size-4" /></span>
    <div class="min-w-0 flex-1 pt-0.5">
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <p class="text-sm font-semibold text-ink-900">{{ $t->type->label() }}
                @if (! $t->asset_id && $t->quantity)<span class="font-normal text-ink-600">· {{ $t->quantity > 0 && $t->type->value === 'adjusted' ? '+' : '' }}{{ number_format($t->quantity) }}</span>@endif
            </p>
            @if ($showItem ?? false)
                <a href="{{ $t->asset ? route('app.inventory.assets.show', $t->asset) : route('app.inventory.equipment.show', $t->equipment) }}" class="text-sm text-brand-700 hover:underline">
                    {{ $t->asset ? $t->asset->asset_tag.' · ' : '' }}{{ $t->equipment->name }}
                </a>
            @endif
        </div>
        @if ($details)<p class="mt-0.5 text-sm text-ink-600">{{ implode(' · ', $details) }}</p>@endif
        @if ($t->note)<p class="mt-1 text-sm text-ink-500 italic">“{{ $t->note }}”</p>@endif
        <p class="mt-1 text-xs text-ink-400">{{ $t->user_name ?? 'System' }} · <time datetime="{{ $t->occurred_at->toIso8601String() }}">{{ Format::datetime($t->occurred_at) }}</time></p>
    </div>
</li>
