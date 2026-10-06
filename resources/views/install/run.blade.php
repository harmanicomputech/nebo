<x-layouts.auth title="Installing">
    @if (! $error)
        {{-- Each page load runs one step; the browser moves on to the next by itself. --}}
        @push('head')<meta http-equiv="refresh" content="{{ $busy ? 3 : 0 }};url={{ route('install.run') }}">@endpush
    @endif
    <h1 class="mt-10 text-2xl font-semibold text-ink-900 lg:mt-0">{{ $error ? 'Installation paused' : 'Installing…' }}</h1>
    <p class="mt-2 text-sm text-ink-500">{{ $error ? 'A step failed. Fix the cause if you can, then try the step again; finished steps are not repeated.' : 'Keep this page open. It moves on by itself.' }}</p>

    @php $percent = (int) round(100 * $done / max(1, count($steps))); @endphp
    <div class="mt-8 h-2 overflow-hidden rounded-full bg-ink-100" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Installation progress">
        <div class="h-full rounded-full bg-brand-600" style="width: {{ $percent }}%"></div>
    </div>
    <p class="mt-2 text-xs text-ink-500 tabular-nums">{{ $done }} of {{ count($steps) }} steps</p>

    <ol class="mt-6 space-y-2 text-sm">
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
        <div class="mt-6 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm break-words text-brand-800" role="alert">{{ \Illuminate\Support\Str::limit($error, 600) }}</div>
        <x-ui.button :href="route('install.run')" class="mt-4 w-full" icon="refresh-cw">Try this step again</x-ui.button>
    @endif
</x-layouts.auth>
