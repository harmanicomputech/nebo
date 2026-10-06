@php use App\Support\Format; @endphp
<x-report :report="$report" :title="$title" :description="$description" :period="$period">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Accepted value" :value="Format::naira($data['summary']['acceptedValue'])" :hint="$data['summary']['accepted'].' quotation(s)'" icon="circle-check" tone="success" />
        <x-ui.stat label="Conversion" :value="$data['summary']['conversion'] !== null ? $data['summary']['conversion'].'%' : '—'" hint="Accepted ÷ answered or expired" icon="gauge" tone="brand" />
        <x-ui.stat label="Average accepted" :value="$data['summary']['average'] !== null ? Format::naira($data['summary']['average']) : '—'" icon="receipt" />
        <x-ui.stat label="Awaiting answer now" :value="Format::naira($data['summary']['pipeline'])" icon="send" tone="warning" />
    </div>
    <x-ui.card title="Accepted value by month" description="By the date the customer accepted.">
        <x-chart.columns label="Accepted quotation value by month" :categories="$data['months']" :series="$data['won']" format="naira" />
    </x-ui.card>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Quotations sent by month"><x-chart.columns label="Quotations sent by month" :categories="$data['months']" :series="$data['sentCount']" /></x-ui.card>
        <x-ui.card title="Top customers by accepted value"><x-chart.bars label="Top customers by accepted value" :items="$data['topCustomers']" format="naira" /></x-ui.card>
    </div>
</x-report>
