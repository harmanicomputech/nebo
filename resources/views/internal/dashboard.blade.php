@php use App\Support\Format; @endphp
<x-layouts.app title="Dashboard">
    {{-- Hero: what is happening right now --}}
    <section class="relative mb-8 overflow-hidden rounded-3xl bg-ink-950 px-6 py-8 text-white sm:px-10 sm:py-10">
        <div class="stage-grid absolute inset-0"></div>
        <div class="stage-beam absolute inset-0 opacity-80"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-6">
            <div>
                <p class="text-xs font-semibold tracking-[0.25em] text-brand-500 uppercase">{{ Format::datetime(now(), 'l, j F Y') }}</p>
                <h1 class="mt-3 text-3xl font-semibold sm:text-4xl">Good {{ now(config('nebo.display_timezone'))->hour < 12 ? 'morning' : (now(config('nebo.display_timezone'))->hour < 17 ? 'afternoon' : 'evening') }}, {{ \Illuminate\Support\Str::before($user->name, ' ') }}.</h1>
                <p class="mt-2 max-w-xl text-sm text-ink-300">What is happening at Nebo Stage right now: today’s events, new requests, inventory and anything that needs attention.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($user->getRoleNames() as $role)
                    <x-ui.badge tone="dark" class="!bg-white/10 !ring-white/15">{{ $role }}</x-ui.badge>
                @endforeach
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Unread notifications" :value="$stats['unread']" icon="bell" tone="brand" :href="route('app.notifications.index', ['filter' => 'unread'])" />
        @if ($stats['activeUsers'] !== null)
            <x-ui.stat label="Active users" :value="$stats['activeUsers']" icon="users" :href="route('app.users.index', ['status' => 'active'])" />
        @endif
        @if ($stats['roles'] !== null)
            <x-ui.stat label="Roles" :value="$stats['roles']" icon="shield-check" :href="route('app.roles.index')" />
        @endif
        @if ($stats['auditToday'] !== null)
            <x-ui.stat label="Audited actions today" :value="$stats['auditToday']" icon="scroll-text" :href="route('app.audit.index', ['from' => today(config('nebo.display_timezone'))->toDateString()])" />
        @endif
    </div>

    @if ($events)
        <section class="mt-8" aria-labelledby="ev-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="ev-heading" class="text-lg font-semibold">Events</h2>
                    <p class="text-sm text-ink-500">{{ $events['week'] }} this week · {{ $events['month'] }} this month · {{ $events['confirmed'] }} confirmed · {{ $events['completed'] }} completed</p>
                </div>
                <div class="flex gap-1">
                    <x-ui.button variant="ghost" size="sm" icon="calendar-days" :href="route('app.calendar')">Calendar</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" icon-right="arrow-right" :href="route('app.events.index')">All events</x-ui.button>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <x-ui.card title="Today" :padding="false">
                    @forelse ($events['today'] as $e)
                        @php $phase = $e->phaseOn(now()); @endphp
                        <a href="{{ route('app.events.show', $e) }}" class="flex items-center gap-3 border-b border-ink-100 px-5 py-3 last:border-0 hover:bg-ink-50">
                            <x-ui.badge :tone="['setup' => 'warning', 'show' => 'brand', 'breakdown' => 'neutral'][$phase] ?? 'neutral'">{{ ucfirst($phase ?? '') }}</x-ui.badge>
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $e->name }}</span><span class="block truncate text-xs text-ink-500">{{ $e->venue }}</span></span>
                        </a>
                    @empty
                        <x-ui.empty-state icon="calendar-days" title="Nothing on today" />
                    @endforelse
                </x-ui.card>
                <x-ui.card title="Coming up" :padding="false">
                    @forelse ($events['upcoming'] as $e)
                        <a href="{{ route('app.events.show', $e) }}" class="flex items-center gap-3 border-b border-ink-100 px-5 py-3 last:border-0 hover:bg-ink-50">
                            <span class="w-16 shrink-0 text-xs font-semibold text-ink-500">{{ \App\Support\Format::datetime($e->setup_starts_at, 'D j M') }}</span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $e->name }}</span><span class="block truncate text-xs text-ink-500">{{ $e->customer?->company ?: $e->customer?->name }}</span></span>
                            <x-ui.badge :tone="$e->status->tone()">{{ $e->status->label() }}</x-ui.badge>
                        </a>
                    @empty
                        <x-ui.empty-state icon="calendar-range" title="No upcoming events" />
                    @endforelse
                </x-ui.card>
            </div>
        </section>
    @endif

    @if ($operations)
        <section class="mt-8" aria-labelledby="ops-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div><h2 id="ops-heading" class="text-lg font-semibold">Operations</h2>
                    <p class="text-sm text-ink-500">{{ number_format($operations['out']) }} items out on events · {{ $operations['inTransit'] }} in transit</p></div>
                <div class="flex gap-1"><x-ui.button variant="ghost" size="sm" icon="layers" :href="route('app.availability')">Availability</x-ui.button><x-ui.button variant="ghost" size="sm" icon-right="arrow-right" :href="route('app.load-lists.index')">Load lists</x-ui.button></div>
            </div>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <x-ui.card title="Deployments in the next 7 days" :padding="false">
                    @forelse ($operations['deployments'] as $list)
                        <a href="{{ route('app.events.load-list', $list->event) }}" class="flex items-center gap-3 border-b border-ink-100 px-5 py-3 last:border-0 hover:bg-ink-50">
                            <span class="w-16 shrink-0 text-xs font-semibold text-ink-500">{{ \App\Support\Format::datetime($list->event->setup_starts_at, 'D j M') }}</span>
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $list->event->name }}</span><span class="block text-xs text-ink-500">{{ $list->items_count }} lines</span></span>
                            <x-ui.badge :tone="$list->status->tone()">{{ $list->status->label() }}</x-ui.badge>
                        </a>
                    @empty
                        <x-ui.empty-state icon="truck" title="No load-outs due" />
                    @endforelse
                </x-ui.card>
                <x-ui.card title="Overdue returns" :padding="false">
                    @forelse ($operations['overdue'] as $eventId => $rows)
                        <a href="{{ route('app.events.returns', $rows->first()->event) }}" class="flex items-center gap-3 border-b border-ink-100 px-5 py-3 last:border-0 hover:bg-brand-50">
                            <x-ui.icon name="triangle-alert" class="size-4 shrink-0 text-brand-600" />
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $rows->first()->event->name }}</span><span class="block text-xs text-ink-500">due back {{ \App\Support\Format::datetime($rows->first()->hold_ends_at) }}</span></span>
                            <span class="text-sm font-semibold text-brand-700">{{ $rows->sum('quantity') }} out</span>
                        </a>
                    @empty
                        <x-ui.empty-state icon="package-check" title="Nothing overdue" />
                    @endforelse
                </x-ui.card>
            </div>
        </section>
    @endif

    @if ($trips !== null && ($trips->isNotEmpty() || auth()->user()->can('logistics.view')))
        <section class="mt-8" aria-labelledby="trips-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div><h2 id="trips-heading" class="text-lg font-semibold">{{ auth()->user()->can('logistics.view') ? 'Trips today and tomorrow' : 'Your trips' }}</h2>
                    <p class="text-sm text-ink-500">Planned, loading and on the road.</p></div>
                <x-ui.button variant="ghost" size="sm" icon-right="arrow-right" :href="route('app.logistics.index')">Logistics</x-ui.button>
            </div>
            <x-ui.card :padding="false">
                @if ($trips->isEmpty())
                    <x-ui.empty-state icon="truck" title="No trips today or tomorrow" />
                @else
                    <ul class="divide-y divide-ink-100">@foreach ($trips as $t)@include('internal.logistics.trips._row', ['t' => $t])@endforeach</ul>
                @endif
            </x-ui.card>
        </section>
    @endif

    @if ($requests)
        <section class="mt-8" aria-labelledby="req-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="req-heading" class="text-lg font-semibold">Production requests</h2>
                    <p class="text-sm text-ink-500">{{ $requests['open'] }} open · {{ $requests['mine'] }} assigned to you</p>
                </div>
                <x-ui.button variant="ghost" size="sm" icon-right="arrow-right" :href="route('app.requests.index')">All requests</x-ui.button>
            </div>
            <x-ui.card :padding="false">
                @if ($requests['recent']->isEmpty())
                    <x-ui.empty-state icon="inbox" title="No open requests" description="New requests from the public form will appear here." />
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach ($requests['recent'] as $r)
                            <li><a href="{{ route('app.requests.show', $r) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                                <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-ink-900">{{ $r->event_name }}</span><span class="block text-xs text-ink-500">{{ $r->company ?: $r->contact_person }} · {{ \App\Support\Format::date($r->event_date) }}</span></span>
                                <x-ui.badge :tone="$r->status->tone()">{{ $r->status->label() }}</x-ui.badge>
                                <span class="w-20 text-right text-xs text-ink-400">{{ $r->created_at->diffForHumans(short: true) }}</span>
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </section>
    @endif

    @if ($inventory)
        @php
            $g = $inventory['groups'];
            $fleet = array_sum($g) - $g['retired'] - $g['lost'];
            $tiles = [
                ['Available now', $inventory['allocatable'], 'circle-check', 'bg-emerald-500', ['group' => 'available']],
                ['Reserved / allocated', $g['committed'], 'clipboard-check', 'bg-sky-500', ['group' => 'committed']],
                ['Out on events', $g['out'], 'truck', 'bg-ink-800', ['group' => 'out']],
                ['Inspection & maintenance', $g['attention'], 'wrench', 'bg-amber-500', ['group' => 'attention']],
                ['Damaged', $g['damaged'], 'triangle-alert', 'bg-brand-600', ['group' => 'damaged']],
                ['Lost / missing', $g['lost'], 'circle-x', 'bg-brand-800', ['group' => 'lost']],
            ];
        @endphp
        <section class="mt-8" aria-labelledby="inv-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="inv-heading" class="text-lg font-semibold">Inventory</h2>
                    <p class="text-sm text-ink-500">{{ number_format($fleet) }} serialized units in the fleet · {{ number_format($inventory['bulk']['available']) }} quantity items in stock</p>
                </div>
                <x-ui.button variant="ghost" size="sm" icon-right="arrow-right" :href="route('app.inventory.equipment.index')">Equipment</x-ui.button>
            </div>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <x-ui.card title="Fleet status" class="xl:col-span-2">
                    <div class="flex h-3 overflow-hidden rounded-full bg-ink-100" role="img" aria-label="Fleet status breakdown">
                        @foreach ($tiles as [$label, $count, $icon, $bar])
                            @if ($count && array_sum($g))<div class="{{ $bar }}" style="width: {{ $count / array_sum($g) * 100 }}%" title="{{ $label }}: {{ $count }}"></div>@endif
                        @endforeach
                    </div>
                    <ul class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($tiles as [$label, $count, $icon, $bar, $query])
                            <li><a href="{{ route('app.inventory.assets.index', $query) }}" class="flex items-center gap-3 rounded-xl border border-ink-100 p-3 hover:border-ink-300">
                                <span class="size-2.5 shrink-0 rounded-full {{ $bar }}" aria-hidden="true"></span>
                                <span class="min-w-0"><span class="block text-xl font-semibold tabular-nums">{{ number_format($count) }}</span><span class="block truncate text-xs text-ink-500">{{ $label }}</span></span>
                            </a></li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-xs text-ink-500">“Available now” counts units whose status and condition allow allocation today. For event dates, see <a href="{{ route('app.availability') }}" class="font-semibold text-brand-700 hover:underline">Availability</a>.</p>
                </x-ui.card>
                <x-ui.card title="Needs attention" :padding="false">
                    <ul class="divide-y divide-ink-100 text-sm">
                        <li class="flex items-center justify-between gap-3 px-5 py-3"><span class="flex items-center gap-2"><x-ui.icon name="triangle-alert" class="size-4 text-brand-600" />Low-stock items</span><a href="{{ route('app.inventory.equipment.index', ['availability' => 'low']) }}" class="font-semibold tabular-nums hover:text-brand-700">{{ $inventory['lowStockCount'] }}</a></li>
                        <li class="flex items-center justify-between gap-3 px-5 py-3"><span class="flex items-center gap-2"><x-ui.icon name="wrench" class="size-4 text-amber-600" />Maintenance due (14 days)</span>@can('maintenance.view')<a href="{{ route('app.maintenance.index', ['view' => 'due']) }}" class="font-semibold tabular-nums hover:text-brand-700">{{ $inventory['maintenanceDue'] }}</a>@else<span class="font-semibold tabular-nums">{{ $inventory['maintenanceDue'] }}</span>@endcan</li>
                        @if ($inventory['openJobs'] !== null)
                            <li class="flex items-center justify-between gap-3 px-5 py-3"><span class="flex items-center gap-2"><x-ui.icon name="hammer" class="size-4 text-amber-600" />Open maintenance jobs</span><a href="{{ route('app.maintenance.index') }}" class="font-semibold tabular-nums hover:text-brand-700">{{ $inventory['openJobs'] }}</a></li>
                        @endif
                        <li class="flex items-center justify-between gap-3 px-5 py-3"><span class="flex items-center gap-2"><x-ui.icon name="package" class="size-4 text-ink-500" />Quarantined stock units</span><span class="font-semibold tabular-nums">{{ number_format($inventory['bulk']['quarantine']) }}</span></li>
                    </ul>
                    @if ($inventory['lowStock']->isNotEmpty())
                        <div class="border-t border-ink-100 px-5 py-4">
                            <p class="mb-2 text-xs font-semibold tracking-wider text-ink-500 uppercase">Lowest stock</p>
                            <ul class="space-y-2">
                                @foreach ($inventory['lowStock'] as $item)
                                    <li class="flex items-center justify-between gap-3 text-sm"><a href="{{ route('app.inventory.equipment.show', $item) }}" class="truncate hover:text-brand-700">{{ $item->name }}</a><span class="shrink-0 text-xs"><span class="font-semibold text-brand-700">{{ $item->availableUnits() }}</span> / min {{ $item->low_stock_threshold }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </x-ui.card>
            </div>
        </section>
    @endif

    <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-ui.card title="Module roadmap" description="What goes live next. Nothing below is active yet." class="xl:col-span-2">
            <ol class="grid gap-4 sm:grid-cols-2">
                @foreach ($roadmap as $module)
                    <li class="flex gap-4 rounded-xl border border-dashed border-ink-200 p-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink-50 text-ink-500 ring-1 ring-ink-100"><x-ui.icon :name="$module['icon']" /></span>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-ink-900">{{ $module['name'] }}</p>
                                <x-ui.badge tone="neutral" :dot="false">Phase {{ $module['phase'] }}</x-ui.badge>
                            </div>
                            <p class="mt-1 text-xs text-ink-500">{{ $module['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        <x-ui.card title="Notifications" :padding="false">
            <x-slot:actions><x-ui.button variant="ghost" size="sm" :href="route('app.notifications.index')">View all</x-ui.button></x-slot:actions>
            @forelse ($notifications as $n)
                <a href="{{ route('app.notifications.open', $n->id) }}" class="flex gap-3 border-b border-ink-100 px-6 py-4 last:border-0 hover:bg-ink-50">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700"><x-ui.icon :name="$n->data['icon'] ?? 'bell'" class="size-4" /></span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-ink-900">{{ $n->data['title'] ?? '' }}</span>
                        <span class="line-clamp-2 block text-xs text-ink-500">{{ $n->data['body'] ?? '' }}</span>
                    </span>
                </a>
            @empty
                <x-ui.empty-state icon="bell" title="You're all caught up" description="New booking requests, conflicts and maintenance alerts will appear here." />
            @endforelse
        </x-ui.card>
    </div>

    @can('audit.view')
        <x-ui.card title="Recent activity" description="Latest audited actions across the system." class="mt-6" :padding="false">
            <x-slot:actions><x-ui.button variant="ghost" size="sm" :href="route('app.audit.index')">Audit log</x-ui.button></x-slot:actions>
            @if ($activity->isEmpty())
                <x-ui.empty-state icon="history" title="No activity yet" />
            @else
                <ol class="divide-y divide-ink-100">
                    @foreach ($activity as $log)
                        <li class="flex items-center gap-4 px-6 py-3">
                            <span class="size-2 shrink-0 rounded-full {{ in_array($log->event, ['deleted', 'login_failed', 'archived']) ? 'bg-brand-600' : 'bg-ink-300' }}" aria-hidden="true"></span>
                            <a href="{{ route('app.audit.show', $log) }}" class="min-w-0 flex-1 truncate text-sm text-ink-800 hover:text-brand-700">{{ $log->description }}</a>
                            <span class="hidden shrink-0 text-xs text-ink-500 sm:block">{{ $log->user_name }}</span>
                            <time class="shrink-0 text-xs text-ink-400" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->diffForHumans() }}</time>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-ui.card>
    @endcan
</x-layouts.app>
