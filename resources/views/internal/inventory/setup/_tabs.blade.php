<nav class="mb-6 flex gap-1 overflow-x-auto border-b border-ink-200" aria-label="Inventory setup">
    @foreach (['categories' => 'Categories', 'locations' => 'Locations', 'statuses' => 'Statuses'] as $key => $label)
        @php $active = request()->routeIs('app.inventory.setup.'.$key); @endphp
        <a href="{{ route('app.inventory.setup.'.$key) }}" @if ($active) aria-current="page" @endif
           @class(['-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold', 'border-brand-600 text-ink-900' => $active, 'border-transparent text-ink-500 hover:text-ink-900' => ! $active])>{{ $label }}</a>
    @endforeach
    @can('settings.view')
        <a href="{{ route('app.settings.options', 'condition') }}" class="-mb-px shrink-0 border-b-2 border-transparent px-4 py-2.5 text-sm font-semibold text-ink-500 hover:text-ink-900">Conditions & units ↗</a>
    @endcan
</nav>
