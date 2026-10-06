<x-report :report="$report" :title="$title" :description="$description" :period="$period">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Overall utilisation" :value="$data['summary']['overall'].'%'" icon="gauge" />
        <x-ui.stat label="Items tracked" :value="$data['summary']['items']" icon="boxes" />
        <x-ui.stat label="Never booked" :value="$data['summary']['idle']" icon="triangle-alert" tone="warning" />
        <x-ui.stat label="Busiest item" :value="$data['summary']['busiest'] ? $data['summary']['busiest']['pct'].'%' : '—'" :hint="$data['summary']['busiest']['name'] ?? null" icon="chart-column" tone="brand" />
    </div>
    <x-ui.card title="Utilisation by item" description="Unit-days booked ÷ unit-days owned in the period. Items near 100% are candidates for buying or hiring in; items near 0% for selling or redeploying.">
        <x-chart.bars label="Utilisation by item" :items="$data['rows']->map(fn ($r) => ['label' => $r['name'], 'value' => $r['pct'], 'hint' => $r['units'].' units', 'url' => route('app.inventory.equipment.show', $r['id'])])->all()" format="percent" :max="100" />
    </x-ui.card>
</x-report>
