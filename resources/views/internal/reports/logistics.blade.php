<x-report :report="$report" :title="$title" :description="$description" :period="$period">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Trips" :value="$data['summary']['trips']" icon="route" />
        <x-ui.stat label="Arrived" :value="$data['summary']['arrived']" icon="map-pin" tone="success" />
        <x-ui.stat label="On time" :value="$data['summary']['onTime'] !== null ? $data['summary']['onTime'].'%' : '—'" hint="Within 30 min of plan" icon="gauge" tone="brand" />
        <x-ui.stat label="Vehicles in service" :value="$data['summary']['vehicles']" icon="car-front" />
    </div>
    <x-ui.card title="Trips by month"><x-chart.columns label="Trips by month" :categories="$data['months']" :series="$data['series']" /></x-ui.card>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Trips by vehicle"><x-chart.bars label="Trips by vehicle" :items="$data['byVehicle']" /></x-ui.card>
        <x-ui.card title="Trips by driver"><x-chart.bars label="Trips by driver" :items="$data['byDriver']" /></x-ui.card>
    </div>
</x-report>
