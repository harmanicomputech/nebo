@php use App\Support\Format; @endphp
<x-layouts.app title="Audit log">
    <x-ui.page-header title="Audit log" description="A permanent, read-only record of sign-ins, changes, status updates and permission changes."
        :breadcrumbs="['Administration' => null, 'Audit log' => null]" />

    <x-ui.card :padding="false">
        <form method="GET" data-filters class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-6 lg:items-end">
            <x-ui.input name="q" type="search" label="Search" :value="$filters['q'] ?? ''" placeholder="Description or record ID" class="lg:col-span-2" />
            <x-ui.select name="event" label="Action" :options="$events->combine($events)->all()" :value="$filters['event'] ?? ''" placeholder="Any action" />
            <x-ui.select name="type" label="Record type" :options="$types->combine($types)->all()" :value="$filters['type'] ?? ''" placeholder="Any type" />
            <x-ui.input name="from" type="date" label="From" :value="$filters['from'] ?? ''" />
            <x-ui.input name="to" type="date" label="To" :value="$filters['to'] ?? ''" />
            <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                @if (array_filter($filters))<x-ui.button variant="ghost" :href="route('app.audit.index')">Clear</x-ui.button>@endif
            </div>
        </form>

        @if ($logs->isEmpty())
            <x-ui.empty-state icon="scroll-text" title="No audit entries found" description="Nothing matches these filters." />
        @else
            <x-ui.table>
                <x-slot:head><th>When</th><th>User</th><th>Action</th><th>Description</th><th>Record</th><th>IP</th></x-slot:head>
                @foreach ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-ink-600"><time datetime="{{ $log->created_at->toIso8601String() }}">{{ Format::datetime($log->created_at) }}</time></td>
                        <td class="whitespace-nowrap font-medium">{{ $log->user_name ?? 'System' }}</td>
                        <td><x-ui.badge :tone="match (true) { in_array($log->event, ['deleted', 'archived', 'login_failed']) => 'danger', in_array($log->event, ['created', 'restored']) => 'success', str_contains($log->event, 'permission') || str_contains($log->event, 'role') => 'warning', default => 'neutral' }">{{ str_replace('_', ' ', $log->event) }}</x-ui.badge></td>
                        <td class="min-w-64"><a href="{{ route('app.audit.show', $log) }}" class="text-ink-800 hover:text-brand-700">{{ $log->description }}</a></td>
                        <td class="whitespace-nowrap text-xs text-ink-500">{{ $log->auditable_type ? $log->auditable_type.' #'.$log->auditable_id : '—' }}</td>
                        <td class="font-mono text-xs text-ink-500">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $logs->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
