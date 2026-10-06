{{-- Read-only lines grouped by section, with totals. $quote with items. --}}
@php use App\Support\Format; @endphp
<div class="space-y-5">
    @foreach ($quote->items->groupBy(fn ($i) => $i->section->value) as $section => $items)
        <section>
            <h3 class="mb-2 text-xs font-semibold tracking-wider text-ink-500 uppercase">{{ $items->first()->section->label() }}</h3>
            {{-- Phones: one line per card; wider screens and print: a table. --}}
            <ul class="divide-y divide-ink-100 rounded-xl border border-ink-100 sm:hidden print:hidden">
                @foreach ($items as $item)
                    <li class="px-4 py-3 text-sm">
                        <p class="font-medium">{{ $item->description }}</p>
                        <p class="mt-0.5 flex justify-between gap-3 text-ink-500"><span>{{ $item->quantity }} × {{ $item->days }} day{{ $item->days === 1 ? '' : 's' }} × {{ Format::naira($item->unit_price_kobo) }}</span><span class="font-semibold text-ink-900 tabular-nums">{{ Format::naira($item->line_total_kobo) }}</span></p>
                    </li>
                @endforeach
            </ul>
            <div class="hidden sm:block print:block"><x-ui.table>
                <table class="table-nebo">
                    <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Days</th><th class="text-right">Unit price</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr><td class="min-w-48">{{ $item->description }}</td><td class="text-right tabular-nums">{{ $item->quantity }}</td><td class="text-right tabular-nums">{{ $item->days }}</td><td class="text-right tabular-nums whitespace-nowrap">{{ Format::naira($item->unit_price_kobo) }}</td><td class="text-right font-medium tabular-nums whitespace-nowrap">{{ Format::naira($item->line_total_kobo) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.table></div>
        </section>
    @endforeach
    <dl class="ml-auto max-w-xs space-y-1.5 text-sm">
        <div class="flex justify-between gap-6"><dt class="text-ink-500">Subtotal</dt><dd class="tabular-nums">{{ Format::naira($quote->subtotal_kobo) }}</dd></div>
        @if ($quote->effectiveDiscountKobo())<div class="flex justify-between gap-6"><dt class="text-ink-500">Discount</dt><dd class="tabular-nums">−{{ Format::naira($quote->effectiveDiscountKobo()) }}</dd></div>@endif
        @if ($quote->tax_rate_bp)<div class="flex justify-between gap-6"><dt class="text-ink-500">VAT {{ $quote->taxPercent() }}%</dt><dd class="tabular-nums">{{ Format::naira($quote->tax_kobo) }}</dd></div>@endif
        <div class="flex justify-between gap-6 border-t border-ink-200 pt-2 text-base font-bold"><dt>Total</dt><dd class="tabular-nums">{{ Format::naira($quote->total_kobo) }}</dd></div>
    </dl>
</div>
