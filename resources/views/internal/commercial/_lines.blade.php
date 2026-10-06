{{--
    Line editor for quotations and packages.
    $lines (list of section, description, quantity, days, unit_price, service_id, equipment_id),
    $sections, $catalogue; optional $totals = ['discount' => '0', 'tax' => '7.5'] to show the
    discount/VAT fields and totals.
--}}
@php
    $initial = old('items', $lines);
    $totals ??= null;
@endphp
<div x-data="lineItems(@js(array_values($initial)), @js($catalogue), @js($totals ? ['discount' => old('discount', $totals['discount']), 'tax' => old('tax_percent', $totals['tax'])] : []))">
    @error('items')<p class="mb-3 text-sm font-medium text-brand-700">{{ $message }}</p>@enderror
    @if ($errors->has('items.*'))<p class="mb-3 text-sm font-medium text-brand-700">Check the highlighted lines: each needs a description, a quantity and days of at least 1, and a price.</p>@endif

    <div class="hidden grid-cols-[8rem_1fr_4.5rem_4.5rem_8rem_7rem_2.5rem] gap-2 px-1 pb-2 text-[11px] font-semibold tracking-wider text-ink-500 uppercase lg:grid">
        <span>Section</span><span>Description</span><span>Qty</span><span>Days</span><span>Unit price (₦)</span><span class="text-right">Line total</span><span></span>
    </div>
    <ol class="space-y-3 lg:space-y-2">
        <template x-for="(row, i) in rows" :key="row.key">
            <li data-row class="grid grid-cols-2 gap-2 rounded-xl border border-ink-100 p-3 lg:grid-cols-[8rem_1fr_4.5rem_4.5rem_8rem_7rem_2.5rem] lg:items-center lg:rounded-none lg:border-0 lg:p-0">
                <select class="field col-span-2 lg:col-span-1" :name="`items[${i}][section]`" x-model="row.section" aria-label="Section">
                    @foreach ($sections as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
                <div class="col-span-2 flex gap-2 lg:col-span-1">
                    <input data-description type="text" class="field min-w-0 flex-1" :name="`items[${i}][description]`" x-model="row.description" maxlength="255" placeholder="Description" aria-label="Description" required>
                    <span class="relative w-11 shrink-0 lg:w-12">
                        <x-ui.icon name="book-open" class="pointer-events-none absolute top-1/2 left-1/2 size-4 -translate-x-1/2 -translate-y-1/2 text-ink-600" />
                        <select class="field h-full w-full cursor-pointer appearance-none px-1 text-transparent" x-on:change="pick(row, $event.target.value); $event.target.value = ''" aria-label="Fill from the catalogue" title="Fill from the catalogue">
                            <option value="">From catalogue…</option>
                            <optgroup label="Equipment">@foreach ($catalogue['equipment'] as $e)<option value="e:{{ $e['id'] }}" class="text-ink-900">{{ $e['name'] }}</option>@endforeach</optgroup>
                            <optgroup label="Services">@foreach ($catalogue['services'] as $s)<option value="s:{{ $s['id'] }}" class="text-ink-900">{{ $s['name'] }}</option>@endforeach</optgroup>
                        </select>
                    </span>
                </div>
                <input type="hidden" :name="`items[${i}][service_id]`" :value="row.service_id ?? ''">
                <input type="hidden" :name="`items[${i}][equipment_id]`" :value="row.equipment_id ?? ''">
                <label class="text-xs text-ink-500 lg:contents"><span class="lg:sr-only">Quantity</span><input type="number" min="1" class="field" :name="`items[${i}][quantity]`" x-model="row.quantity" required></label>
                <label class="text-xs text-ink-500 lg:contents"><span class="lg:sr-only">Days</span><input type="number" min="1" class="field" :name="`items[${i}][days]`" x-model="row.days" required></label>
                <label class="text-xs text-ink-500 lg:contents"><span class="lg:sr-only">Unit price (₦)</span><input type="text" inputmode="decimal" class="field" :name="`items[${i}][unit_price]`" x-model="row.unit_price" placeholder="0" required></label>
                <p class="self-end text-right text-sm font-semibold tabular-nums lg:self-center" x-text="money(line(row))"></p>
                <button type="button" class="col-span-2 justify-self-end rounded-lg p-2 text-ink-400 hover:bg-brand-50 hover:text-brand-700 lg:col-span-1" x-on:click="remove(i)" aria-label="Remove line"><x-ui.icon name="trash-2" class="size-4" /></button>
            </li>
        </template>
    </ol>
    <p x-show="rows.length === 0" class="rounded-xl border border-dashed border-ink-200 px-4 py-6 text-center text-sm text-ink-500">No lines yet. Add services, equipment, crew and transport.</p>

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($sections as $value => $label)
            <x-ui.button size="sm" variant="secondary" icon="plus" x-on:click="add('{{ $value }}')">{{ $label }}</x-ui.button>
        @endforeach
    </div>

    @if ($totals)
        <div class="mt-6 grid gap-6 border-t border-ink-100 pt-6 sm:grid-cols-2">
            <div class="grid grid-cols-2 gap-4 self-start">
                <label class="block text-sm font-medium text-ink-800">Discount (₦)<input type="text" inputmode="decimal" name="discount" x-model="discount" class="field mt-1.5" placeholder="0"></label>
                <label class="block text-sm font-medium text-ink-800">VAT (%)<input type="text" inputmode="decimal" name="tax_percent" x-model="tax" class="field mt-1.5"></label>
            </div>
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-ink-500">Subtotal</dt><dd class="tabular-nums" x-text="money(subtotal)"></dd></div>
                <div class="flex justify-between" x-show="discountValue > 0"><dt class="text-ink-500">Discount</dt><dd class="tabular-nums" x-text="'−' + money(discountValue)"></dd></div>
                <div class="flex justify-between"><dt class="text-ink-500">VAT <span x-text="tax + '%'"></span></dt><dd class="tabular-nums" x-text="money(taxValue)"></dd></div>
                <div class="flex justify-between border-t border-ink-100 pt-2 text-base font-semibold"><dt>Total</dt><dd class="tabular-nums" x-text="money(total)"></dd></div>
                <p class="text-xs text-ink-500">Totals are checked again when you save.</p>
            </dl>
        </div>
    @endif
</div>
