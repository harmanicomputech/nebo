@php $tz = config('nebo.display_timezone'); $today = now($tz)->startOfDay(); @endphp
<x-layouts.app title="Availability">
    <x-ui.page-header title="Equipment availability" description="Free units per item for the next two weeks, after every booking. Pick a date, category or item." :breadcrumbs="['Operations' => null, 'Availability' => null]" />

    <x-ui.card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5 lg:items-end">
            <x-ui.input label="From" name="date" type="date" :value="$from->toDateString()" />
            <x-ui.select label="Category" name="category" :options="$categories" :value="$filters['category'] ?? ''" placeholder="All categories" />
            <x-ui.select label="Item" name="equipment" :options="$equipmentOptions" :value="$filters['equipment'] ?? ''" placeholder="All items" />
            <x-ui.input label="Search" name="q" type="search" :value="$filters['q'] ?? ''" />
            <div class="flex gap-2"><x-ui.button type="submit" variant="dark" icon="filter">Show</x-ui.button>
                <x-ui.button variant="ghost" icon="chevron-right" :href="route('app.availability', array_merge($filters, ['date' => $from->addDays(14)->toDateString()]))">Next 2 weeks</x-ui.button></div>
        </form>
        <div class="flex flex-wrap gap-4 border-b border-ink-100 px-4 py-2 text-xs text-ink-600 sm:px-6" aria-label="Legend">
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-emerald-100 ring-1 ring-emerald-300"></span>All free</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-amber-100 ring-1 ring-amber-300"></span>Some booked</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-brand-100 ring-1 ring-brand-300"></span>None free</span>
            <span>Each cell shows free / total.</span>
        </div>
        @if ($items->isEmpty())
            <x-ui.empty-state icon="layers" title="No equipment found" />
        @else
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 min-w-44 bg-white px-4 py-2 text-left text-[11px] font-semibold tracking-wider text-ink-500 uppercase">Equipment</th>
                            @foreach (range(0, 13) as $i)
                                @php $d = $from->addDays($i); @endphp
                                <th @class(['px-1 py-2 text-center font-semibold', 'text-brand-700' => $d->equalTo($today), 'text-ink-500' => ! $d->equalTo($today)])><span class="block text-[10px] uppercase">{{ $d->format('D') }}</span>{{ $d->format('j') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr class="border-t border-ink-100">
                                <th scope="row" class="sticky left-0 z-10 bg-white px-4 py-2 text-left font-medium"><a href="{{ route('app.inventory.equipment.show', $item) }}" class="hover:text-brand-700">{{ Str::limit($item->name, 28) }}</a></th>
                                @foreach ($timeline[$item->id] ?? [] as $cell)
                                    @php $tone = $cell['total'] === 0 ? 'bg-ink-50 text-ink-400' : ($cell['available'] === 0 ? 'bg-brand-100 text-brand-800' : ($cell['available'] < $cell['total'] ? 'bg-amber-100 text-amber-900' : 'bg-emerald-50 text-emerald-800')); @endphp
                                    <td class="p-0.5"><span class="block rounded px-1 py-1.5 text-center font-semibold tabular-nums {{ $tone }}" title="{{ $cell['date']->format('D j M') }}: {{ $cell['available'] }} free of {{ $cell['total'] }}, {{ $cell['held'] }} booked">{{ $cell['available'] }}<span class="font-normal">/{{ $cell['total'] }}</span></span></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $items->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
