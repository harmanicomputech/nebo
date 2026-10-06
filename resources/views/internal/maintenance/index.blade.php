@php use App\Support\Format; $u = auth()->user(); @endphp
<x-layouts.app title="Maintenance">
    <x-ui.page-header title="Maintenance" description="Faults, repairs, inspections and scheduled servicing." :breadcrumbs="['Inventory' => null, 'Maintenance' => null]">
        <x-slot:actions>
            @can('create', App\Models\MaintenanceRecord::class)<x-ui.button icon="plus" :href="route('app.maintenance.create')">Log a job</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Open jobs" :value="$stats['open']" icon="wrench" />
        <x-ui.stat label="In progress" :value="$stats['in_progress']" icon="hammer" tone="warning" />
        <x-ui.stat label="High or urgent" :value="$stats['urgent']" icon="triangle-alert" tone="brand" />
        @if ($stats['overdue'] !== null)
            <x-ui.stat label="Overdue schedules" :value="$stats['overdue']" icon="calendar-clock" :href="route('app.maintenance.index', ['view' => 'due'])" />
        @endif
    </div>

    <x-ui.card :padding="false">
        <nav class="flex gap-1 overflow-x-auto border-b border-ink-100 px-4 sm:px-6" aria-label="View">
            @foreach ($views as $key => $label)
                <a href="{{ route('app.maintenance.index', ['view' => $key]) }}" @if ($view === $key) aria-current="page" @endif
                   @class(['-mb-px shrink-0 border-b-2 px-3 py-3 text-sm font-semibold', 'border-brand-600 text-ink-900' => $view === $key, 'border-transparent text-ink-500 hover:text-ink-900' => $view !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" data-filters class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-6 lg:items-end">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="relative sm:col-span-2">
                <label for="q" class="sr-only">Search</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="{{ in_array($view, ['open', 'closed']) ? 'Reference, asset tag, item or issue' : 'Asset tag or item' }}" class="field pl-9">
            </div>
            <x-ui.select name="type" :options="$types" :value="$filters['type'] ?? ''" placeholder="Any type" aria-label="Type" />
            @if (in_array($view, ['open', 'closed']))
                <x-ui.select name="priority" :options="$priorities" :value="$filters['priority'] ?? ''" placeholder="Any priority" aria-label="Priority" />
                <x-ui.select name="technician" :options="$technicians" :value="$filters['technician'] ?? ''" placeholder="Any technician" aria-label="Technician" />
            @endif
            <div class="flex items-center gap-3">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                @if (collect($filters)->filter()->isNotEmpty())<a href="{{ route('app.maintenance.index', ['view' => $view]) }}" class="text-sm font-semibold text-brand-700 hover:underline">Clear</a>@endif
            </div>
        </form>

        @isset($records)
            @if ($records->isEmpty())
                <x-ui.empty-state icon="wrench" :title="$view === 'open' ? 'No open jobs' : 'No closed jobs'" :description="$view === 'open' ? 'Log a fault from here or from an asset page, or record an inspection.' : null" />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($records as $r)
                        <li><a href="{{ route('app.maintenance.show', $r) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-4 hover:bg-ink-50 sm:px-6">
                            <span class="min-w-0 flex-1 basis-60">
                                <span class="block font-semibold text-ink-900"><span class="font-mono">{{ $r->asset->asset_tag }}</span> · {{ $r->issue }}</span>
                                <span class="block truncate text-xs text-ink-500"><span class="font-mono">{{ $r->reference }}</span> · {{ $r->equipment->name }} · {{ $r->typeLabel() }}@if ($r->technician) · {{ $r->technician->name }}@endif</span>
                                <span class="mt-0.5 block text-xs text-ink-500">
                                    @if ($r->status->value === 'completed') Completed {{ Format::datetime($r->completed_at, 'j M Y') }}
                                    @elseif ($r->scheduled_starts_at) {{ Format::datetime($r->scheduled_starts_at, 'D j M, g:ia') }} → {{ Format::datetime($r->scheduled_ends_at, 'D j M, g:ia') }}
                                    @else Reported {{ Format::datetime($r->created_at, 'j M, g:ia') }} @endif
                                </span>
                            </span>
                            <span class="flex items-center gap-2">
                                <x-ui.badge :tone="$r->priority->tone()">{{ $r->priority->label() }}</x-ui.badge>
                                <x-ui.badge :tone="$r->status->tone()">{{ $r->status->label() }}</x-ui.badge>
                            </span>
                        </a></li>
                    @endforeach
                </ul>
                @if ($records->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $records->links() }}</div>@endif
            @endif
        @else
            @if ($schedules->isEmpty())
                <x-ui.empty-state icon="calendar-check" :title="$view === 'due' ? 'Nothing due in the next 30 days' : 'No schedules yet'" description="Add recurring maintenance from an asset page, for one unit or every unit of an item." />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($schedules as $s)
                        @php $open = $s->records->first(); $overdue = $s->is_active && $s->next_due_on->lt($today); @endphp
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-4 sm:px-6">
                            <span class="min-w-0 flex-1 basis-60">
                                <a href="{{ route('app.inventory.assets.show', $s->asset) }}" class="block font-semibold text-ink-900 hover:text-brand-700"><span class="font-mono">{{ $s->asset->asset_tag }}</span> · {{ $s->asset->equipment->name }}</a>
                                <span class="block text-xs text-ink-500">{{ $s->typeLabel() }} every {{ $s->interval_days }} days · last done {{ $s->last_done_on ? $s->last_done_on->format('j M Y') : 'never' }}</span>
                            </span>
                            <span @class(['text-sm tabular-nums', 'font-semibold text-brand-700' => $overdue, 'text-ink-600' => ! $overdue])>{{ $s->is_active ? ($overdue ? 'Overdue · ' : 'Due ').$s->next_due_on->format('j M Y') : 'Paused' }}</span>
                            @if ($open)
                                <a href="{{ route('app.maintenance.show', $open) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ $open->reference }}</a>
                            @elseif ($s->is_active && $u->can('maintenance.manage'))
                                <form method="POST" action="{{ route('app.maintenance.schedules.job', $s) }}" data-once>@csrf<x-ui.button type="submit" size="sm" variant="secondary" icon="plus">Open job</x-ui.button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($schedules->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $schedules->links() }}</div>@endif
            @endif
        @endisset
    </x-ui.card>
</x-layouts.app>
