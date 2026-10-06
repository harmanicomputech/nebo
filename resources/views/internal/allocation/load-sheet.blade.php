@php use App\Support\Format; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>Load sheet {{ $list->reference }} · {{ config('nebo.brand.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 12mm; } @media print { .no-print { display: none !important; } body { background: #fff; } } tr { break-inside: avoid; }</style>
</head>
<body class="bg-ink-100 text-ink-900">
    <div class="no-print sticky top-0 flex items-center justify-between bg-ink-950 px-6 py-3 text-white">
        <p class="text-sm">Load sheet · {{ $list->reference }}</p>
        <button type="button" onclick="window.print()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold">Print</button>
    </div>
    <main class="mx-auto max-w-[190mm] bg-white p-8 print:p-0">
        <header class="flex items-start justify-between border-b-4 border-brand-600 pb-4">
            <div><x-ui.logo /><p class="mt-3 text-xs tracking-[0.2em] text-ink-500 uppercase">Production load sheet</p></div>
            <div class="text-right text-sm"><p class="font-mono text-lg font-bold">{{ $list->reference }}</p><p>Status: {{ $list->status->label() }}</p><p class="text-xs text-ink-500">Printed {{ Format::datetime(now()) }}</p></div>
        </header>
        <section class="mt-5 grid grid-cols-2 gap-4 text-sm">
            <div><p class="text-xs font-semibold text-ink-500 uppercase">Event</p><p class="font-semibold">{{ $event->name }}</p><p class="font-mono text-xs">{{ $event->reference }}</p><p>{{ $event->customer?->company ?: $event->customer?->name }}</p></div>
            <div><p class="text-xs font-semibold text-ink-500 uppercase">Venue & dates</p><p>{{ $event->venue }}</p><p>Setup {{ Format::datetime($event->setup_starts_at) }}</p><p>Show {{ Format::datetime($event->starts_at) }}</p></div>
        </section>
        @if ($event->team->isNotEmpty())
            <p class="mt-4 text-sm"><span class="text-xs font-semibold text-ink-500 uppercase">Team:</span> {{ $event->team->map(fn ($m) => $m->staff->name.' ('.$m->roleLabel().')')->implode(', ') }}</p>
        @endif
        <table class="mt-6 w-full border-collapse text-sm">
            <thead><tr class="border-b-2 border-ink-900 text-left text-xs uppercase"><th class="py-2">Equipment</th><th>Asset / qty</th><th>Case</th><th>From</th><th class="w-16 text-center">Picked</th><th class="w-16 text-center">Loaded</th><th class="w-16 text-center">Checked</th></tr></thead>
            <tbody>
                @foreach ($groups as $name => $items)
                    @foreach ($items->sortBy(fn ($i) => $i->allocation->asset?->asset_tag) as $item)
                        @php $rank = $item->status->rank(); @endphp
                        <tr class="border-b border-ink-200">
                            <td class="py-1.5">{{ $loop->first ? $name : '' }}</td>
                            <td class="font-mono">{{ $item->allocation->asset?->asset_tag ?? $item->allocation->quantity }}</td>
                            <td>{{ $item->case_label }}</td>
                            <td class="text-xs">{{ $item->allocation->location?->code }}</td>
                            @foreach ([1, 2, 3] as $r)<td class="text-center">{{ $rank >= $r ? '✓' : '☐' }}</td>@endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        <footer class="mt-10 grid grid-cols-3 gap-6 text-xs">
            @foreach (['Prepared by' => $list->preparer?->name, 'Checked by' => '', 'Driver' => ''] as $label => $value)
                <div><p class="h-8 border-b border-ink-400">{{ $value }}</p><p class="mt-1 text-ink-500">{{ $label }} · signature & date</p></div>
            @endforeach
        </footer>
    </main>
</body>
</html>
