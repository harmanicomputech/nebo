<x-layouts.app title="Loading sample data">
    @unless ($error)
        {{-- One step per page load; the page moves on by itself. --}}
        @push('head')<meta http-equiv="refresh" content="0;url={{ route('app.settings.system.sample.run') }}">@endpush
    @endunless
    <x-ui.page-header :title="$error ? 'Loading paused' : 'Loading sample data…'" :description="$error ? 'A step failed. You can try it again; finished steps are not repeated.' : 'Keep this page open. It takes a minute or two.'" />

    @php $percent = (int) round(100 * $done / max(1, count($steps))); @endphp
    <x-ui.card>
        <div class="h-2 overflow-hidden rounded-full bg-ink-100" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress">
            <div class="h-full rounded-full bg-brand-600" style="width: {{ $percent }}%"></div>
        </div>
        <p class="mt-2 text-xs text-ink-500 tabular-nums">{{ $done }} of {{ count($steps) }} steps</p>
        <ol class="mt-4 space-y-2 text-sm">
            @foreach ($steps as $i => $step)
                <li @class(['flex items-center gap-2', 'text-ink-500' => $i > $done, 'font-semibold text-ink-900' => $i === $done, 'text-ink-600' => $i < $done])>
                    @if ($i < $done)<x-ui.icon name="circle-check" class="size-4 shrink-0 text-emerald-600" />
                    @elseif ($i === $done && $error)<x-ui.icon name="circle-x" class="size-4 shrink-0 text-brand-600" />
                    @elseif ($i === $done)<x-ui.icon name="loader-circle" class="size-4 shrink-0 animate-spin text-brand-600" />
                    @else<x-ui.icon name="circle" class="size-4 shrink-0" />@endif
                    {{ $step['label'] }}
                </li>
            @endforeach
        </ol>
        @if ($error)
            <div class="mt-5 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm break-words text-brand-800" role="alert">{{ Str::limit($error, 600) }}</div>
            <x-ui.button :href="route('app.settings.system.sample.run')" class="mt-4" icon="refresh-cw">Try this step again</x-ui.button>
        @endif
    </x-ui.card>
</x-layouts.app>
