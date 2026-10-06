@php use App\Support\Format; @endphp
<x-layouts.app title="Events">
    <x-ui.page-header title="Events" description="Productions from planning to breakdown." :breadcrumbs="['Operations' => null, 'Events' => null]">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="calendar-days" :href="route('app.calendar')">Calendar</x-ui.button>
            @can('create', App\Models\Event::class)<x-ui.button icon="plus" :href="route('app.events.create')">New event</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <nav class="flex gap-1 overflow-x-auto border-b border-ink-100 px-4 sm:px-6" aria-label="When">
            @foreach (['upcoming' => 'Upcoming', 'today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'past' => 'Past', 'all' => 'All'] as $key => $label)
                <a href="{{ route('app.events.index', array_merge(request()->except(['when', 'page']), ['when' => $key])) }}" @if ($when === $key) aria-current="page" @endif
                   @class(['-mb-px shrink-0 border-b-2 px-3 py-3 text-sm font-semibold', 'border-brand-600 text-ink-900' => $when === $key, 'border-transparent text-ink-500 hover:text-ink-900' => $when !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" data-filters class="grid gap-3 border-b border-ink-100 p-4 sm:grid-cols-2 sm:px-6 lg:grid-cols-5 lg:items-end">
            <input type="hidden" name="when" value="{{ $when }}">
            <div class="relative sm:col-span-2">
                <label for="q" class="sr-only">Search events</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Event, reference, venue or client" class="field pl-9">
            </div>
            <x-ui.select name="status" :options="$statuses" :value="$filters['status'] ?? ''" placeholder="Any status" aria-label="Status" />
            <x-ui.select name="type" :options="$types" :value="$filters['type'] ?? ''" placeholder="Any type" aria-label="Type" />
            <div class="flex items-center gap-3">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                @if (collect($filters)->except('when')->filter()->isNotEmpty())<a href="{{ route('app.events.index', ['when' => $when]) }}" class="text-sm font-semibold text-brand-700 hover:underline">Clear</a>@endif
            </div>
        </form>

        @if ($events->isEmpty())
            <x-ui.empty-state icon="calendar-range" title="No events found" :description="auth()->user()->can('events.view') ? 'Create an event, or convert a confirmed request.' : 'Events you are assigned to will appear here.'" />
        @else
            <ul class="divide-y divide-ink-100">
                @foreach ($events as $event)
                    @php $start = $event->starts_at->copy()->setTimezone(config('nebo.display_timezone')); @endphp
                    <li><a href="{{ route('app.events.show', $event) }}" class="flex items-center gap-4 px-4 py-4 hover:bg-ink-50 sm:px-6">
                        <span class="grid w-14 shrink-0 place-items-center rounded-xl bg-ink-950 py-2 text-white">
                            <span class="text-[10px] font-semibold tracking-widest text-brand-400 uppercase">{{ $start->format('M') }}</span>
                            <span class="font-display text-xl leading-none font-bold">{{ $start->format('j') }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-ink-900">{{ $event->name }}</span>
                            <span class="block truncate text-xs text-ink-500">{{ $event->reference }} · {{ $event->customer?->company ?: $event->customer?->name }} · {{ $event->venue }}</span>
                            <span class="mt-1 block text-xs text-ink-500">Setup {{ Format::datetime($event->setup_starts_at, 'j M, g:ia') }} → breakdown {{ Format::datetime($event->breakdown_ends_at, 'j M, g:ia') }} · {{ $event->team_count }} crew</span>
                            <x-ui.badge :tone="$event->status->tone()" class="mt-1.5 sm:hidden">{{ $event->status->label() }}</x-ui.badge>
                        </span>
                        <x-ui.badge :tone="$event->status->tone()" class="max-sm:hidden">{{ $event->status->label() }}</x-ui.badge>
                    </a></li>
                @endforeach
            </ul>
            @if ($events->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $events->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
