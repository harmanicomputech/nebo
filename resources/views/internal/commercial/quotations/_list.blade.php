{{-- Compact quotation list. $quotes; $newUrl (null = no button) --}}
@php use App\Support\Format; @endphp
<x-ui.card title="Quotations" :padding="false">
    @if ($newUrl)
        <x-slot:actions><x-ui.button size="sm" icon="plus" :href="$newUrl">New quotation</x-ui.button></x-slot:actions>
    @endif
    @if ($quotes->isEmpty())
        <p class="px-5 py-4 text-sm text-ink-500 sm:px-6">No quotations yet.</p>
    @else
        <ul class="divide-y divide-ink-100">
            @foreach ($quotes as $q)
                <li><a href="{{ route('app.quotations.show', $q) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                    <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $q->title }}</span><span class="block text-xs text-ink-500"><span class="font-mono">{{ $q->label() }}</span> · {{ Format::date($q->updated_at) }}</span></span>
                    <span class="text-sm font-semibold tabular-nums">{{ Format::naira($q->total_kobo) }}</span>
                    <x-ui.badge :tone="$q->status->tone()">{{ $q->status->label() }}</x-ui.badge>
                </a></li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
