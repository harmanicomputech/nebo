{{--
    Column chart, single or stacked (D64).
    :categories  list of x labels (e.g. months)
    :series      list of ['name' => ..., 'values' => [...]] — colours follow the fixed order
    format       number | naira
    label        accessible name for the chart
--}}
@props(['categories', 'series', 'format' => 'number', 'label'])
@php
    use App\Support\Charts;
    $totals = collect($categories)->keys()->map(fn ($i) => collect($series)->sum(fn ($s) => $s['values'][$i] ?? 0));
    $scale = Charts::scale((float) $totals->max());
    $many = count($categories) > 6;
@endphp
<figure {{ $attributes->merge(['class' => 'chart']) }} aria-label="{{ $label }}">
    @if (count($series) > 1)
        <ul class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-600" aria-hidden="true">
            @foreach ($series as $i => $s)
                <li class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background: {{ Charts::SERIES[$i] }}"></span>{{ $s['name'] }}</li>
            @endforeach
        </ul>
    @endif
    <div class="relative h-52 pl-11">
        @foreach ($scale['ticks'] as $tick)
            <div aria-hidden="true" class="absolute right-0 left-11 border-t" style="bottom: {{ $tick / $scale['max'] * 100 }}%; border-color: {{ Charts::GRID }}">
                <span class="absolute -left-11 w-9 -translate-y-1/2 text-right text-[11px] text-ink-400 tabular-nums">{{ Charts::compact($tick, $format) }}</span>
            </div>
        @endforeach
        <div class="absolute inset-y-0 right-0 left-11 flex items-end">
            @foreach ($categories as $i => $category)
                @php
                    $parts = collect($series)->map(fn ($s, $si) => ['name' => $s['name'], 'value' => $s['values'][$i] ?? 0, 'color' => Charts::SERIES[$si]]);
                    $tip = $category.': '.$parts->map(fn ($p) => (count($series) > 1 ? $p['name'].' ' : '').Charts::full($p['value'], $format))->implode(' · ');
                    $visible = $parts->filter(fn ($p) => $p['value'] > 0)->values();
                @endphp
                <div class="group flex h-full min-w-0 flex-1 cursor-default flex-col items-center justify-end outline-none" tabindex="0" role="img" aria-label="{{ $tip }}" data-tip="{{ $tip }}">
                    <div class="flex w-full max-w-6 flex-col-reverse gap-[2px] transition group-hover:brightness-110 group-focus-visible:ring-2 group-focus-visible:ring-ink-900" style="height: {{ $totals[$i] / $scale['max'] * 100 }}%">
                        @foreach ($visible as $vi => $p)
                            <div @class(['w-full min-h-[2px]', 'rounded-t-[4px]' => $vi === $visible->count() - 1]) style="flex: {{ $p['value'] }} 1 0; background: {{ $p['color'] }}"></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="mt-1.5 flex pl-11 text-[11px] text-ink-500" aria-hidden="true">
        @foreach ($categories as $i => $category)
            <span @class(['min-w-0 flex-1 truncate text-center', 'max-sm:invisible' => $many && $i % 2 === 1])>{{ $category }}</span>
        @endforeach
    </div>
    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-xs font-semibold text-ink-500 hover:text-ink-900">View as table</summary>
        <x-ui.table class="mt-2">
            <table class="table-nebo">
                <caption class="sr-only">{{ $label }}</caption>
                <thead><tr><th></th>@foreach ($series as $s)<th class="text-right">{{ $s['name'] }}</th>@endforeach @if (count($series) > 1)<th class="text-right">Total</th>@endif</tr></thead>
                <tbody>
                    @foreach ($categories as $i => $category)
                        <tr><th scope="row" class="text-left font-medium">{{ $category }}</th>@foreach ($series as $s)<td class="text-right tabular-nums">{{ Charts::full($s['values'][$i] ?? 0, $format) }}</td>@endforeach @if (count($series) > 1)<td class="text-right font-semibold tabular-nums">{{ Charts::full($totals[$i], $format) }}</td>@endif</tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.table>
    </details>
</figure>
