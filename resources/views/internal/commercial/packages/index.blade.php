@php use App\Support\Format; @endphp
<x-layouts.app title="Packages">
    <x-ui.page-header title="Production packages" description="Priced bundles that fill a quotation in one step. Prices are a starting point; every quotation can be adjusted." :breadcrumbs="['Commercial' => null, 'Packages' => null]">
        <x-slot:actions>
            @can('create', App\Models\ProductionPackage::class)<x-ui.button icon="plus" :href="route('app.packages.create')">New package</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>
    @if ($packages->isEmpty())
        <x-ui.card><x-ui.empty-state icon="package" title="No packages yet" description="Create bundles such as a conference AV package or a wedding stage and lighting package." /></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($packages as $p)
                <x-ui.card @class(['opacity-60' => ! $p->is_active])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-semibold">{{ $p->name }}</h2>
                            <p class="text-xs text-ink-500">{{ $p->event_type ? ($eventTypes[$p->event_type] ?? $p->event_type) : 'Any event' }} · {{ $p->items->count() }} lines</p>
                        </div>
                        @unless ($p->is_active)<x-ui.badge tone="neutral">Hidden</x-ui.badge>@endunless
                    </div>
                    @if ($p->description)<p class="mt-3 line-clamp-3 text-sm text-ink-600">{{ $p->description }}</p>@endif
                    <p class="mt-4 text-xl font-semibold tabular-nums">{{ Format::naira($p->totalKobo()) }} <span class="text-xs font-normal text-ink-500">before VAT</span></p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @can('create', App\Models\Quotation::class)@if ($p->is_active)<x-ui.button size="sm" icon="receipt" :href="route('app.quotations.create', ['package' => $p->id])">Quote with it</x-ui.button>@endif @endcan
                        @can('update', $p)<x-ui.button size="sm" variant="secondary" icon="pencil" :href="route('app.packages.edit', $p)">Edit</x-ui.button>@endcan
                        @can('delete', $p)<x-ui.confirm size="sm" variant="ghost" icon="archive" :action="route('app.packages.destroy', $p)" method="DELETE" title="Archive {{ $p->name }}?" message="It won't be offered for new quotations. Existing quotations keep their lines." confirm="Archive">Archive</x-ui.confirm>@endcan
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
