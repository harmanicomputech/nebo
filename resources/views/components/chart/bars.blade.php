{{--
    Ranked horizontal bars, one series (D64). Value at the tip, label on the left
    (above the bar on phones). :items list of ['label' => , 'value' => , 'url' => ?, 'hint' => ?].
    format number | naira | percent; :max fixes the scale (e.g. 100 for percentages).
--}}
@props(['items', 'format' => 'number', 'label', 'max' => null, 'empty' => 'No data for this period.'])
@php
    use App\Support\Charts;
    $top = (float) ($max ?? collect($items)->max('value')) ?: 1;
@endphp
<figure {{ $attributes->merge(['class' => 'chart']) }} aria-label="{{ $label }}">
    @if (collect($items)->isEmpty())
        <p class="py-6 text-center text-sm text-ink-500">{{ $empty }}</p>
    @else
        <ul class="space-y-2.5">
            @foreach ($items as $item)
                @php $pct = max(0, min(100, $item['value'] / $top * 100)); @endphp
                <li class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1 text-sm sm:grid-cols-[minmax(0,12rem)_1fr_auto]">
                    <span class="min-w-0 truncate text-ink-700 sm:order-none" title="{{ $item['label'] }}">
                        @if (! empty($item['url']))<a href="{{ $item['url'] }}" class="hover:text-brand-700 hover:underline">{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif
                        @if (! empty($item['hint']))<span class="text-xs text-ink-400"> · {{ $item['hint'] }}</span>@endif
                    </span>
                    <span class="text-right font-semibold text-ink-900 tabular-nums sm:order-last">{{ Charts::full($item['value'], $format) }}</span>
                    <span class="col-span-2 h-3 sm:col-span-1" tabindex="0" data-tip="{{ $item['label'] }}: {{ Charts::full($item['value'], $format) }}">
                        <span class="block h-full rounded-r-[4px] transition hover:brightness-110" style="width: {{ $pct }}%; min-width: {{ $item['value'] > 0 ? '2px' : '0' }}; background: {{ Charts::SERIES[0] }}"></span>
                    </span>
                </li>
            @endforeach
        </ul>
        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-xs font-semibold text-ink-500 hover:text-ink-900">View as table</summary>
            <x-ui.table class="mt-2">
                <table class="table-nebo">
                    <caption class="sr-only">{{ $label }}</caption>
                    <tbody>@foreach ($items as $item)<tr><th scope="row" class="text-left font-medium">{{ $item['label'] }}</th><td class="text-right tabular-nums">{{ Charts::full($item['value'], $format) }}</td></tr>@endforeach</tbody>
                </table>
            </x-ui.table>
        </details>
    @endif
</figure>
