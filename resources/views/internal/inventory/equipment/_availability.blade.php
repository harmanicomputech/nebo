{{-- Availability meter. Colour is never the only signal: the numbers and the "Low" label carry it too. --}}
@php
    $total = $item->totalUnits();
    $available = $item->availableUnits();
    $pct = $total ? min(100, round($available / $total * 100)) : 0;
@endphp
<div>
    <div class="flex items-baseline justify-between gap-2 text-xs">
        <span><span class="text-sm font-semibold text-ink-900 tabular-nums">{{ number_format($available) }}</span> <span class="text-ink-500">of {{ number_format($total) }} {{ Str::plural(strtolower($item->unitLabel()), $total) }}</span></span>
        @if ($item->trashed())
            <x-ui.badge tone="neutral" :dot="false">Archived</x-ui.badge>
        @elseif ($item->isLowStock())
            <x-ui.badge tone="danger">Low</x-ui.badge>
        @elseif ($total && ! $available)
            <x-ui.badge tone="warning">None free</x-ui.badge>
        @endif
    </div>
    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="{{ $available }} of {{ $total }} available">
        <div class="h-full rounded-full {{ $item->isLowStock() ? 'bg-brand-600' : 'bg-emerald-500' }}" style="width: {{ $pct }}%"></div>
    </div>
</div>
