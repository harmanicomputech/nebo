<x-report :report="$report" :title="$title" :description="$description" :period="$period">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Events" :value="$data['summary']['total']" icon="calendar-range" />
        <x-ui.stat label="Completed" :value="$data['summary']['completed']" icon="check" tone="success" />
        <x-ui.stat label="Cancelled" :value="$data['summary']['cancelled']" icon="circle-x" />
        <x-ui.stat label="Requests won" :value="$data['summary']['winRate'] !== null ? $data['summary']['winRate'].'%' : '—'" icon="inbox" tone="brand" />
    </div>
    <x-ui.card title="Events by month" description="By start date.">
        <x-chart.columns label="Events by month" :categories="$data['months']" :series="$data['series']" />
    </x-ui.card>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Request pipeline" description="Requests received in the period and how far they got.">
            <x-chart.bars label="Request pipeline" :items="$data['funnel']" />
        </x-ui.card>
        <x-ui.card title="Events by type"><x-chart.bars label="Events by type" :items="$data['byType']" /></x-ui.card>
    </div>
    <x-ui.card title="Top customers by events"><x-chart.bars label="Top customers by events" :items="$data['byCustomer']" /></x-ui.card>
</x-report>
