<nav class="mb-6 flex gap-1 overflow-x-auto border-b border-ink-200" aria-label="Settings">
    @php $onOptions = request()->routeIs('app.settings.options*'); @endphp
    <a href="{{ route('app.settings.edit') }}" @if (! $onOptions) aria-current="page" @endif @class(['-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold', 'border-brand-600 text-ink-900' => ! $onOptions, 'border-transparent text-ink-500 hover:text-ink-900' => $onOptions])>General</a>
    <a href="{{ route('app.settings.options') }}" @if ($onOptions) aria-current="page" @endif @class(['-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold', 'border-brand-600 text-ink-900' => $onOptions, 'border-transparent text-ink-500 hover:text-ink-900' => ! $onOptions])>Option lists</a>
</nav>
