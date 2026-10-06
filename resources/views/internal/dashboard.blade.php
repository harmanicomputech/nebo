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
                <p class="mt-2 max-w-xl text-sm text-ink-300">Nebo Stage operations at a glance. Live event, inventory and maintenance panels appear here as each module goes live.</p>
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
