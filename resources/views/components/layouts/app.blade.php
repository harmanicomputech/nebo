@props(['title' => null])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="h-full" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[70] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

{{-- Mobile backdrop --}}
<div x-cloak x-show="nav" x-transition.opacity class="fixed inset-0 z-40 bg-ink-950/60 lg:hidden" x-on:click="nav = false"></div>

{{-- Sidebar --}}
<aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-ink-950 text-ink-300 transition-transform duration-200 lg:translate-x-0"
       :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" aria-label="Main navigation">
    <div class="flex h-16 items-center justify-between border-b border-white/5 px-5">
        <a href="{{ route('app.dashboard') }}"><x-ui.logo dark /></a>
        <button type="button" class="rounded-md p-1.5 text-ink-400 hover:bg-white/5 hover:text-white lg:hidden" x-on:click="nav = false" aria-label="Close menu"><x-ui.icon name="x" /></button>
    </div>
    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @foreach ($navigation['sections'] as $section)
            <div>
                <p class="px-3 pb-2 text-[10.5px] font-semibold tracking-[0.16em] text-ink-500 uppercase">{{ $section['label'] }}</p>
                <ul class="space-y-0.5">
                    @foreach ($section['items'] as $item)
                        @php $active = request()->routeIs($item['route'], $item['active']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                               class="group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-white/[0.07] text-white' : 'hover:bg-white/[0.04] hover:text-white' }}">
                                @if ($active)<span class="absolute inset-y-1.5 left-0 w-0.5 rounded-full bg-brand-600"></span>@endif
                                <x-ui.icon :name="$item['icon']" class="size-[18px] {{ $active ? 'text-brand-500' : 'text-ink-500 group-hover:text-ink-300' }}" />
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        @if ($navigation['planned'])
            {{-- Modules from later phases: listed so people know what's coming, never clickable. --}}
            <div x-data="{ open: false }">
                <button type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[10.5px] font-semibold tracking-[0.16em] text-ink-500 uppercase hover:text-ink-300" x-on:click="open = !open" :aria-expanded="open">
                    Coming next <span class="flex items-center gap-1.5 normal-case tracking-normal">{{ count($navigation['planned']) }} modules <span class="transition" :class="open && 'rotate-180'"><x-ui.icon name="chevron-down" class="size-3.5" /></span></span>
                </button>
                <ul x-cloak x-show="open" x-transition class="mt-1 space-y-0.5">
                    @foreach ($navigation['planned'] as $item)
                        <li class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-1.5 text-sm text-ink-600" title="Planned for phase {{ $item['phase'] }}">
                            <x-ui.icon :name="$item['icon']" class="size-4 text-ink-700" />
                            {{ $item['label'] }}
                            <span class="ml-auto text-[10px] font-semibold tracking-wider text-ink-600 uppercase">Phase {{ $item['phase'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </nav>
    <div class="border-t border-white/5 p-4">
        <a href="{{ route('app.profile.edit') }}" class="flex items-center gap-3 rounded-lg p-2 hover:bg-white/[0.04]">
            <x-ui.avatar :user="auth()->user()" class="ring-ink-950" />
            <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</span>
                <span class="block truncate text-xs text-ink-500">{{ auth()->user()->getRoleNames()->first() ?? 'No role' }}</span>
            </span>
        </a>
    </div>
</aside>

<div class="lg:pl-72">
    {{-- Top bar --}}
    <header class="sticky top-0 z-30 border-b border-ink-100 bg-white/85 backdrop-blur-md">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button type="button" class="-ml-1 rounded-lg p-2 text-ink-600 hover:bg-ink-100 lg:hidden" x-on:click="nav = true" aria-label="Open menu"><x-ui.icon name="menu" /></button>

            <form action="{{ route('app.search') }}" method="GET" role="search" class="relative max-w-xl flex-1"
                  x-data="globalSearch('{{ route('app.search') }}')" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <label for="global-search" class="sr-only">Search</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="global-search" name="q" type="search" autocomplete="off" placeholder="Search equipment, events, customers, people…"
                       x-model="q" x-on:input="onInput()" x-on:focus="if (groups.length) open = true"
                       class="w-full rounded-lg border border-ink-200 bg-ink-50/60 py-2 pr-3 pl-9 text-sm placeholder:text-ink-400 focus:border-brand-600 focus:bg-white focus:ring-4 focus:ring-brand-600/10 focus:outline-none">
                <div x-cloak x-show="open" x-transition class="absolute inset-x-0 top-full mt-2 max-h-[70vh] overflow-y-auto rounded-xl border border-ink-100 bg-white p-2 shadow-lift">
                    <template x-if="loading && !groups.length">
                        <div class="space-y-2 p-3"><div class="skeleton h-4 w-2/3"></div><div class="skeleton h-4 w-1/2"></div></div>
                    </template>
                    <template x-if="!loading && !groups.length">
                        <p class="p-3 text-sm text-ink-500">No results for “<span x-text="q"></span>”.</p>
                    </template>
                    <template x-for="group in groups" :key="group.label">
                        <div class="py-1">
                            <p class="px-3 py-1 text-[11px] font-semibold tracking-wider text-ink-400 uppercase" x-text="group.label"></p>
                            <template x-for="r in group.results" :key="r.url">
                                <a :href="r.url" class="block rounded-lg px-3 py-2 hover:bg-ink-50">
                                    <span class="block text-sm font-medium text-ink-900" x-text="r.title"></span>
                                    <span class="block text-xs text-ink-500" x-text="r.subtitle"></span>
                                </a>
                            </template>
                        </div>
                    </template>
                </div>
            </form>

            <div class="ml-auto flex items-center gap-1">
                <div x-data="installApp" x-cloak x-show="deferred">
                    <x-ui.button variant="ghost" size="sm" icon="monitor-down" x-on:click="install()" class="hidden sm:inline-flex">Install app</x-ui.button>
                </div>

                <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
                    <button type="button" class="relative rounded-lg p-2 text-ink-600 hover:bg-ink-100" x-on:click="open = !open" :aria-expanded="open" aria-label="Notifications ({{ $unreadCount }} unread)">
                        <x-ui.icon name="bell" />
                        @if ($unreadCount)
                            <span class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] leading-4 font-bold text-white ring-2 ring-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                        @endif
                    </button>
                    <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-ink-100 bg-white shadow-lift">
                        <div class="flex items-center justify-between border-b border-ink-100 px-4 py-3">
                            <p class="text-sm font-semibold">Notifications</p>
                            @if ($unreadCount)
                                <form method="POST" action="{{ route('app.notifications.read-all') }}">@csrf<button class="text-xs font-semibold text-brand-700 hover:underline">Mark all read</button></form>
                            @endif
                        </div>
                        <ul class="max-h-80 divide-y divide-ink-100 overflow-y-auto">
                            @forelse ($recentNotifications as $n)
                                <li><a href="{{ route('app.notifications.open', $n->id) }}" class="flex gap-3 px-4 py-3 hover:bg-ink-50">
                                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-ink-100 text-ink-700"><x-ui.icon :name="$n->data['icon'] ?? 'bell'" class="size-4" /></span>
                                    <span class="min-w-0"><span class="block text-sm font-medium text-ink-900">{{ $n->data['title'] ?? 'Notification' }}</span>
                                    <span class="line-clamp-2 block text-xs text-ink-500">{{ $n->data['body'] ?? '' }}</span>
                                    <span class="mt-0.5 block text-[11px] text-ink-400">{{ $n->created_at->diffForHumans() }}</span></span>
                                </a></li>
                            @empty
                                <li class="px-4 py-8 text-center text-sm text-ink-500">You're all caught up.</li>
                            @endforelse
                        </ul>
                        <a href="{{ route('app.notifications.index') }}" class="block border-t border-ink-100 px-4 py-2.5 text-center text-xs font-semibold text-ink-700 hover:bg-ink-50">View all notifications</a>
                    </div>
                </div>

                <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
                    <button type="button" class="flex items-center gap-2 rounded-lg p-1 hover:bg-ink-100" x-on:click="open = !open" :aria-expanded="open" aria-label="Account menu">
                        <x-ui.avatar :user="auth()->user()" size="sm" />
                        <x-ui.icon name="chevron-down" class="hidden size-4 text-ink-400 sm:block" />
                    </button>
                    <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-ink-100 bg-white shadow-lift">
                        <div class="border-b border-ink-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-ink-500">{{ auth()->user()->email }}</p>
                        </div>
                        <a href="{{ route('app.profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-ink-50"><x-ui.icon name="user-cog" class="size-4 text-ink-500" />Profile & password</a>
                        <a href="{{ route('home') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-ink-50"><x-ui.icon name="eye" class="size-4 text-ink-500" />View public site</a>
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-ink-100">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-brand-700 hover:bg-brand-50"><x-ui.icon name="log-out" class="size-4" />Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main id="main" class="px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="mx-auto max-w-7xl">
            {{ $slot }}
        </div>
    </main>
</div>

@include('partials.toasts')
</body>
</html>
