@php use App\Support\QrCode; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Asset labels · {{ config('nebo.brand.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4; margin: 10mm; }
        @media print { .no-print { display: none !important; } body { background: #fff; } }
        .label { break-inside: avoid; }
    </style>
</head>
<body class="bg-ink-100">
    <div class="no-print sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 bg-ink-950 px-6 py-3 text-white">
        <p class="text-sm"><strong>{{ $assets->count() }}</strong> {{ Str::plural('label', $assets->count()) }}{{ $equipment ? ' · '.$equipment->name : '' }}</p>
        <div class="flex gap-2">
            <button type="button" onclick="history.length > 1 ? history.back() : window.close()" class="rounded-lg border border-white/20 px-4 py-2 text-sm font-semibold">Back</button>
            <button type="button" onclick="window.print()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold">Print labels</button>
        </div>
    </div>

    @if ($assets->isEmpty())
        <p class="p-10 text-center text-ink-500">No assets to label.</p>
    @else
        <main class="mx-auto grid max-w-[190mm] grid-cols-3 gap-[4mm] bg-white p-[6mm] print:p-0">
            @foreach ($assets as $asset)
                <div class="label flex items-center gap-2 rounded border border-ink-300 p-2">
                    <div class="shrink-0 [&_svg]:size-[22mm]">{!! QrCode::svg(route('app.scan', $asset->qr_token), 120) !!}</div>
                    <div class="min-w-0 leading-tight">
                        <p class="text-[8px] font-bold tracking-widest text-brand-600 uppercase">Nebo Stage</p>
                        <p class="font-mono text-sm font-bold">{{ $asset->asset_tag }}</p>
                        <p class="truncate text-[9px] text-ink-700">{{ $asset->equipment->name }}</p>
                        @if ($asset->serial_number)<p class="truncate font-mono text-[8px] text-ink-500">S/N {{ $asset->serial_number }}</p>@endif
                    </div>
                </div>
            @endforeach
        </main>
    @endif
</body>
</html>
