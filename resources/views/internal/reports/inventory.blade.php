@php use App\Support\Format; @endphp
<x-report :report="$report" :title="$title" :description="$description">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Serialized units" :value="number_format($data['summary']['units'])" icon="qr-code" />
        <x-ui.stat label="In service" :value="number_format($data['summary']['inService'])" icon="check" tone="success" />
        <x-ui.stat label="Need attention" :value="number_format($data['summary']['attention'])" icon="wrench" tone="warning" />
        @if ($costs)<x-ui.stat label="Fleet value" :value="Format::naira($data['summary']['value'])" icon="receipt" />@endif
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Units by status"><x-chart.bars label="Units by status" :items="$data['byGroup']" /></x-ui.card>
        <x-ui.card title="Units by category"><x-chart.bars label="Units by category" :items="$data['byCategory']" /></x-ui.card>
    </div>
    @if ($costs)
        <x-ui.card title="Value by category" description="Current value, else purchase cost, else replacement value."><x-chart.bars label="Fleet value by category" :items="$data['valueByCategory']" format="naira" /></x-ui.card>
    @endif
</x-report>
