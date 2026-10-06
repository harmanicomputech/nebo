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

        <div class="space-y-6">
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
