@php use App\Support\Format; @endphp
<x-layouts.app title="Audit entry">
    <x-ui.page-header :title="$log->description" :breadcrumbs="['Administration' => null, 'Audit log' => route('app.audit.index'), 'Entry #'.$log->id => null]" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card title="Details">
            <dl class="space-y-3 text-sm">
                @foreach ([
                    'When' => Format::datetime($log->created_at, 'j M Y, g:i:s a'),
                    'User' => $log->user_name ?? 'System',
                    'Action' => str_replace('_', ' ', $log->event),
                    'Record' => $log->auditable_type ? $log->auditable_type.' #'.$log->auditable_id : '—',
                    'IP address' => $log->ip_address ?? '—',
                    'Device' => $log->user_agent ?? '—',
                    'URL' => $log->url ?? '—',
                ] as $label => $value)
                    <div><dt class="text-xs font-semibold tracking-wider text-ink-500 uppercase">{{ $label }}</dt><dd class="mt-0.5 break-words text-ink-800">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-ui.card>

        <x-ui.card title="Changes" class="lg:col-span-2" :padding="false">
            @if ($log->changedKeys() === [])
                <x-ui.empty-state icon="file-text" title="No field changes recorded" description="This entry records an action rather than a change to fields." />
            @else
                <x-ui.table>
                    <x-slot:head><th>Field</th><th>Before</th><th>After</th></x-slot:head>
                    @foreach ($log->changedKeys() as $key)
                        <tr>
                            <td class="font-mono text-xs font-semibold">{{ $key }}</td>
                            <td class="text-xs text-ink-500"><pre class="font-mono break-all whitespace-pre-wrap">{{ json_encode($log->old_values[$key] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></td>
                            <td class="text-xs text-ink-800"><pre class="font-mono break-all whitespace-pre-wrap">{{ json_encode($log->new_values[$key] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
