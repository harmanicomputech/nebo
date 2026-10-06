{{-- Frame for a report page: header, one filter row (period first), export. --}}
@props(['report', 'title', 'description', 'period' => null])
@php use App\Services\Reports\ReportPeriod; @endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title" :description="$description" :breadcrumbs="['Reports' => route('app.reports.index'), $title => null]">
        <x-slot:actions>
            @can('reports.export')
                <x-ui.button variant="secondary" icon="download" :href="route('app.reports.show', array_merge(['report' => $report, 'export' => 'csv'], $period?->query() ?? []))" data-no-busy>Export CSV</x-ui.button>
            @endcan
            <x-ui.button variant="ghost" icon="printer" x-data x-on:click="window.print()">Print</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($period)
        <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 print:hidden" x-data="{ preset: @js($period->preset) }">
            <nav class="flex flex-wrap gap-1" aria-label="Period">
                @foreach (ReportPeriod::PRESETS as $key => $label)
                    @continue($key === 'custom')
                    <a href="{{ route('app.reports.show', ['report' => $report, 'period' => $key]) }}" @if ($period->preset === $key) aria-current="page" @endif
                       @class(['rounded-lg px-3 py-2 text-sm font-semibold', 'bg-ink-900 text-white' => $period->preset === $key, 'bg-white text-ink-600 ring-1 ring-ink-200 hover:bg-ink-50' => $period->preset !== $key])>{{ $label }}</a>
                @endforeach
            </nav>
            <input type="hidden" name="period" value="custom">
            <label class="text-xs text-ink-500">From<input type="date" name="from" value="{{ $period->from->toDateString() }}" class="field mt-1 py-2" required></label>
            <label class="text-xs text-ink-500">To<input type="date" name="to" value="{{ $period->to->toDateString() }}" class="field mt-1 py-2" required></label>
            <x-ui.button type="submit" variant="secondary">Apply</x-ui.button>
        </form>
        <p class="-mt-3 mb-6 text-sm text-ink-500">{{ $period->label() }}</p>
    @endif

    <div class="space-y-6">{{ $slot }}</div>
</x-layouts.app>
