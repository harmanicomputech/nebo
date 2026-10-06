@php use App\Support\Format; @endphp
<x-report :report="$report" :title="$title" :description="$description" :period="$period">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Jobs reported" :value="$data['summary']['reported']" icon="wrench" />
        <x-ui.stat label="Completed" :value="$data['summary']['completed']" icon="check" tone="success" />
        <x-ui.stat label="Average turnaround" :value="$data['summary']['turnaround'] !== null ? $data['summary']['turnaround'].' days' : '—'" icon="history" />
        @if ($costs)
            <x-ui.stat label="Repair cost" :value="Format::naira($data['summary']['cost'])" icon="receipt" tone="warning" />
        @else
            <x-ui.stat label="Open now" :value="$data['summary']['open']" icon="hammer" tone="warning" />
        @endif
    </div>
    <x-ui.card title="Jobs by month"><x-chart.columns label="Maintenance jobs by month" :categories="$data['months']" :series="$data['series']" /></x-ui.card>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Items with most jobs"><x-chart.bars label="Items with most maintenance jobs" :items="$data['byItem']" /></x-ui.card>
        <x-ui.card title="Jobs by type"><x-chart.bars label="Maintenance jobs by type" :items="$data['byType']" /></x-ui.card>
    </div>
    @if ($costs)
        <x-ui.card title="Repair cost by item" description="Completed jobs with a recorded cost."><x-chart.bars label="Repair cost by item" :items="$data['costByItem']" format="naira" /></x-ui.card>
    @endif
</x-report>
