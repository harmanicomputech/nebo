@php
    $phaseStyle = ['setup' => 'border-l-amber-500 bg-amber-50 text-amber-900', 'show' => 'border-l-brand-600 bg-brand-50 text-brand-900', 'breakdown' => 'border-l-ink-500 bg-ink-100 text-ink-800', 'maintenance' => 'border-l-sky-600 bg-sky-50 text-sky-900', 'trip' => 'border-l-emerald-600 bg-emerald-50 text-emerald-900'];
    $phaseLabel = ['setup' => 'Setup', 'show' => 'Show', 'breakdown' => 'Breakdown', 'maintenance' => 'Maintenance', 'trip' => 'Trip'];
    $q = fn ($v, $d) => route('app.calendar', ['view' => $v, 'date' => $d]);
@endphp
<x-layouts.app title="Calendar">
    <x-ui.page-header title="Production calendar" :breadcrumbs="['Overview' => null, 'Calendar' => null]">
        <x-slot:actions>
            <div class="inline-flex rounded-lg border border-ink-200 bg-white p-0.5 text-sm" role="group" aria-label="View">
                @foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $v => $label)
                    <a href="{{ $q($v, $date->toDateString()) }}" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-ink-900 text-white' => $view === $v, 'text-ink-600 hover:text-ink-900' => $view !== $v]) @if ($view === $v) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-ui.button variant="secondary" size="sm" icon="chevron-left" :href="$q($view, $prev)" aria-label="Previous" />
            <x-ui.button variant="secondary" size="sm" :href="$q($view, $today->toDateString())">Today</x-ui.button>
            <x-ui.button variant="secondary" size="sm" icon="chevron-right" :href="$q($view, $next)" aria-label="Next" />
            <h2 class="ml-2 text-lg font-semibold">{{ $title }}</h2>
        </div>
        <ul class="flex flex-wrap gap-3 text-xs" aria-label="Legend">
            @foreach ($phaseLabel as $key => $label)<li class="flex items-center gap-1.5"><span class="h-3 w-1 rounded {{ explode(' ', $phaseStyle[$key])[0] }} border-l-4"></span>{{ $label }}</li>@endforeach
        </ul>
    </div>

    @if ($view === 'month')
        <x-ui.card :padding="false" class="overflow-hidden">
            <div class="grid grid-cols-7 border-b border-ink-100 bg-ink-50 text-center text-[11px] font-semibold tracking-wider text-ink-500 uppercase">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<div class="py-2"><span class="sm:hidden">{{ substr($d, 0, 1) }}</span><span class="hidden sm:inline">{{ $d }}</span></div>@endforeach
            </div>
            <div class="grid grid-cols-7">
                @foreach ($days as $day)
                    @php $isToday = $day['date']->isSameDay($today); $inMonth = $day['date']->month === $month; @endphp
                    <div @class(['min-h-20 border-b border-r border-ink-100 p-1 sm:min-h-28 sm:p-1.5', 'bg-ink-50/50' => ! $inMonth])>
                        <a href="{{ $q('day', $day['date']->toDateString()) }}" @class(['mb-1 inline-grid size-6 place-items-center rounded-full text-xs', 'bg-brand-600 font-bold text-white' => $isToday, 'text-ink-400' => ! $inMonth && ! $isToday, 'text-ink-700' => $inMonth && ! $isToday]) aria-label="{{ $day['date']->format('j F') }}{{ $isToday ? ' (today)' : '' }}">{{ $day['date']->day }}</a>
                        {{-- Phones: a count and phase bars; the day view has the detail. --}}
                        @if ($day['entries'])
                            <a href="{{ $q('day', $day['date']->toDateString()) }}" class="block sm:hidden" aria-label="{{ count($day['entries']) }} scheduled on {{ $day['date']->format('j F') }}">
                                <span class="block text-[10px] font-bold text-ink-700">{{ count($day['entries']) }}</span>
                                <span class="mt-0.5 flex flex-col gap-0.5">@foreach (array_slice($day['entries'], 0, 3) as $e)<span class="block h-1 w-full rounded-full {{ ['setup' => 'bg-amber-500', 'show' => 'bg-brand-600', 'breakdown' => 'bg-ink-500', 'maintenance' => 'bg-sky-600', 'trip' => 'bg-emerald-600'][$e['phase']] }}"></span>@endforeach</span>
                            </a>
                        @endif
                        <ul class="hidden space-y-0.5 sm:block">
                            @foreach (array_slice($day['entries'], 0, 3) as $e)
                                <li><a href="{{ $e['url'] }}" class="block truncate rounded border-l-4 px-1 py-0.5 text-[10px] leading-tight font-medium sm:text-[11px] {{ $phaseStyle[$e['phase']] }}" title="{{ $phaseLabel[$e['phase']] }}: {{ $e['title'] }}">
                                    <span class="hidden font-semibold sm:inline">{{ $phaseLabel[$e['phase']] }} ·</span> @if ($e['shortage'])⚠<span class="sr-only">Equipment not fully allocated</span> @endif{{ $e['title'] }}</a></li>
                            @endforeach
                            @if (count($day['entries']) > 3)<li><a href="{{ $q('day', $day['date']->toDateString()) }}" class="px-1 text-[10px] font-semibold text-ink-500">+{{ count($day['entries']) - 3 }} more</a></li>@endif
                        </ul>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @else
        <div @class(['grid gap-4', 'md:grid-cols-7' => $view === 'week'])>
            @foreach ($days as $day)
                @php $isToday = $day['date']->isSameDay($today); @endphp
                <x-ui.card :padding="false">
                    <div @class(['flex items-center justify-between border-b border-ink-100 px-4 py-2', 'bg-brand-50' => $isToday])>
                        <a href="{{ $q('day', $day['date']->toDateString()) }}" class="text-sm font-semibold">{{ $day['date']->format($view === 'week' ? 'D j' : 'l j F') }}</a>
                        @if ($isToday)<x-ui.badge tone="brand" :dot="false">Today</x-ui.badge>@endif
                    </div>
                    <ul class="space-y-2 p-3">
                        @forelse ($day['entries'] as $e)
                            <li><a href="{{ $e['url'] }}" class="block rounded-lg border-l-4 px-2.5 py-2 text-sm {{ $phaseStyle[$e['phase']] }}">
                                <span class="block text-[11px] font-semibold tracking-wider uppercase">{{ $phaseLabel[$e['phase']] }}@if ($e['time']) · {{ $e['time'] }}@endif</span>
                                <span class="block font-semibold">{{ $e['title'] }}</span>
                                <span class="block text-xs opacity-80">{{ $e['subtitle'] }}</span>
                                @if ($e['shortage'])<span class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand-700"><x-ui.icon name="triangle-alert" class="size-3.5" />Equipment not fully allocated</span>@endif
                            </a></li>
                        @empty
                            <li class="py-2 text-xs text-ink-400">Nothing scheduled</li>
                        @endforelse
                    </ul>
                </x-ui.card>
            @endforeach
        </div>
    @endif
    <p class="mt-4 text-xs text-ink-500">⚠ marks events whose equipment requirements aren't fully allocated..</p>
</x-layouts.app>
