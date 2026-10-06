<x-layouts.app title="System">
    <x-ui.page-header title="Settings" description="Server health, and applying a new version after you upload it."
        :breadcrumbs="['Administration' => null, 'Settings' => auth()->user()->can('settings.view') ? route('app.settings.edit') : null, 'System' => null]" />
    @include('internal.settings._tabs')

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <x-ui.card title="Readiness checks" description="Fail means unsafe or broken for real customers; warn means a feature is limited." :padding="false">
            <ul class="divide-y divide-ink-100">
                @foreach ($checks as [$status, $label, $detail])
                    <li class="flex items-start gap-3 px-5 py-3 text-sm sm:px-6">
                        @if ($status === 'ok')<x-ui.badge tone="success">OK</x-ui.badge>@elseif ($status === 'warn')<x-ui.badge tone="warning">Warn</x-ui.badge>@else<x-ui.badge tone="danger">Fail</x-ui.badge>@endif
                        <span class="min-w-0"><span class="block font-medium text-ink-900">{{ $label }}</span>
                            @if ($status !== 'ok' && $detail)<span class="block text-xs break-words text-ink-500">{{ $detail }}</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        <div class="space-y-6 max-lg:order-first">
            @if ($sample)
                @php
                    $c = $sample['counts'];
                    $parts = collect(['events' => 'events', 'event_requests' => 'requests', 'customers' => 'customers', 'quotations' => 'quotations', 'equipment' => 'equipment items', 'assets' => 'units', 'logistics_trips' => 'trips', 'maintenance_records' => 'repair jobs', 'users' => 'sign-in accounts'])
                        ->filter(fn ($label, $table) => ($c[$table] ?? 0) > 0)->map(fn ($label, $table) => number_format($c[$table]).' '.$label);
                @endphp
                <x-ui.card title="Sample data">
                    <p class="text-sm text-ink-600">The system holds sample records for trying it out: {{ $parts->join(', ', ' and ') }}. Clearing removes all of them and everything they created; records you added yourself stay.</p>
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer font-semibold text-ink-800">Sample sign-in accounts</summary>
                        <p class="mt-2 text-xs text-ink-500">Password for all: <code class="rounded bg-ink-100 px-1.5 py-0.5 font-mono text-ink-800">{{ $sample['password'] }}</code></p>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($sample['accounts'] as $account)
                                <li class="min-w-0"><span class="block truncate font-medium text-ink-900">{{ $account->email }}</span><span class="block text-xs text-ink-500">{{ $account->roles->pluck('name')->join(', ') }}</span></li>
                            @endforeach
                        </ul>
                    </details>
                    @if ($sample['blockers'])
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                            <p class="font-semibold">Some records you added use sample data</p>
                            <p class="mt-1">{{ App\Support\SampleData::describe($sample['blockers']) }}. Switch them to your own customers, equipment and events first, or remove them together with the sample data.</p>
                        </div>
                        <x-ui.confirm class="mt-4 w-full" :action="route('app.settings.system.sample.clear')" method="DELETE" :fields="['include_mine' => 1]" title="Clear sample data and these records?"
                            :message="'Every sample record is deleted for good, together with these records of yours that use it: '.App\Support\SampleData::describe($sample['blockers']).'. Back up the database first if you might need them.'"
                            confirm="Clear everything listed" icon="trash-2">Clear sample data and these records</x-ui.confirm>
                    @else
                        <x-ui.confirm class="mt-4 w-full" :action="route('app.settings.system.sample.clear')" method="DELETE" title="Clear all sample data?"
                            message="Every sample event, request, customer, quote, item, trip, repair and sample account is deleted for good. Records you added yourself stay. Back up the database first if you might want it again."
                            confirm="Clear sample data" icon="trash-2">Clear sample data</x-ui.confirm>
                    @endif
                </x-ui.card>
            @endif

            <x-ui.card title="Apply an update">
                <p class="text-sm text-ink-600">After uploading a new version of the files, run the update. It applies database changes and refreshes roles, permissions and reference data. Your data is kept.</p>
                <p class="mt-3 text-sm font-semibold {{ $pending ? 'text-brand-700' : 'text-ink-700' }}">{{ $pending ? $pending.' database '.str('change')->plural($pending).' waiting' : 'The database is up to date.' }}</p>
                <x-ui.confirm class="mt-4 w-full" :action="route('app.settings.system.update')" title="Apply the update?" message="Back up the database first (DirectAdmin → Backups or phpMyAdmin export). This takes up to a minute." confirm="Apply update" variant="primary" icon="refresh-cw">Apply update</x-ui.confirm>
            </x-ui.card>

            <x-ui.card title="About this installation">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Version</dt><dd class="text-right font-medium text-ink-900">{{ $version ?? 'Development copy' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">PHP</dt><dd class="text-right font-medium text-ink-900">{{ $php }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Database</dt><dd class="min-w-0 text-right font-medium break-words text-ink-900">{{ $database }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Emails waiting to send</dt><dd class="text-right font-medium text-ink-900 tabular-nums">{{ $queued }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Failed emails / jobs</dt><dd class="text-right font-medium text-ink-900 tabular-nums">{{ $failed }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-500">Background jobs</dt><dd class="text-right font-medium text-ink-900">{{ config('nebo.web_cron') ? 'Run on page visits' : 'Cron only' }}</dd></div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
