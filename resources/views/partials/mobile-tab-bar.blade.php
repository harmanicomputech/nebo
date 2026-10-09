{{--
    Phone bottom navigation (D70): four role-aware tabs and "More", which
    opens a sheet with every module, the account and sign-out.
--}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-ink-100 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md select-none print:hidden lg:hidden" aria-label="Main">
    <ul class="mx-auto grid h-16 max-w-xl grid-cols-5">
        @foreach ($mobile['tabs'] as $tab)
            <li>
                <a href="{{ route($tab['route']) }}" @if ($tab['active']) aria-current="page" @endif data-tab
                   class="flex h-full flex-col items-center justify-center gap-1 text-[11px] font-semibold">
                    <span class="tab-pill grid h-7 w-14 place-items-center rounded-full">
                        <x-ui.icon :name="$tab['icon']" class="size-[22px]" />
                    </span>
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
        <li class="{{ ['', 'col-start-2', 'col-start-3', 'col-start-4', 'col-start-5'][count($mobile['tabs'])] }}">
            <button type="button" x-on:click="more = true" :aria-expanded="more" aria-haspopup="dialog" data-tab @if ($mobile['moreActive']) aria-current="page" @endif
                    class="flex h-full w-full flex-col items-center justify-center gap-1 text-[11px] font-semibold">
                <span class="tab-pill relative grid h-7 w-14 place-items-center rounded-full">
                    <x-ui.icon name="layout-grid" class="size-[22px]" />
                </span>
                More
            </button>
        </li>
    </ul>
</nav>

{{-- "More" sheet --}}
<div x-cloak x-show="more" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="All modules">
    <div x-show="more" x-transition.opacity class="absolute inset-0 bg-ink-950/50" x-on:click="more = false"></div>
    <div x-show="more" x-trap.noscroll="more"
         x-transition:enter="transition duration-250 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-200 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="absolute inset-x-0 bottom-0 flex max-h-[88vh] flex-col rounded-t-3xl bg-ink-50 shadow-2xl">
        <div class="flex justify-center pt-2.5 pb-1"><span class="h-1.5 w-10 rounded-full bg-ink-300" aria-hidden="true"></span></div>
        <div class="flex items-center justify-between px-5 pb-2">
            <h2 class="text-lg font-semibold">Menu</h2>
            <button type="button" class="grid size-10 place-items-center rounded-full bg-ink-100 text-ink-700" x-on:click="more = false" aria-label="Close"><x-ui.icon name="x" class="size-5" /></button>
        </div>
        <div class="flex-1 overflow-y-auto overscroll-contain px-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
            <a href="{{ route('app.profile.edit') }}" class="flex items-center gap-3 rounded-2xl bg-white p-3 shadow-card active:bg-ink-50">
                <x-ui.avatar :user="auth()->user()" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-ink-900">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-ink-500">{{ auth()->user()->getRoleNames()->first() ?? 'No role' }} · Profile & password</span>
                </span>
                <x-ui.icon name="chevron-right" class="size-4 text-ink-300" />
            </a>

            @foreach ($navigation['sections'] as $section)
                <p class="mt-5 mb-2 px-1 text-[11px] font-semibold tracking-wider text-ink-500 uppercase">{{ $section['label'] }}</p>
                <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    @foreach ($section['items'] as $item)
                        @php $active = request()->routeIs($item['route'], ...(array) $item['active']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                               class="flex h-full flex-col items-center gap-2 rounded-2xl bg-white px-1.5 py-3 text-center text-xs font-medium shadow-card active:bg-ink-50 {{ $active ? 'text-brand-700 ring-2 ring-brand-600' : 'text-ink-800' }}">
                                <span class="grid size-10 place-items-center rounded-xl {{ $active ? 'bg-brand-600 text-white' : 'bg-ink-900 text-white' }}"><x-ui.icon :name="$item['icon']" class="size-5" /></span>
                                <span class="leading-tight">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach

            <div class="mt-5 divide-y divide-ink-100 overflow-hidden rounded-2xl bg-white shadow-card">
                <a href="{{ route('app.notifications.index') }}" class="flex items-center gap-3 px-4 py-3.5 text-sm font-medium active:bg-ink-50">
                    <x-ui.icon name="bell" class="size-5 text-ink-500" /> Notifications
                    <span data-unread-badge data-full @if (! $unreadCount) hidden @endif class="ml-auto rounded-full bg-brand-600 px-2 py-0.5 text-xs font-bold text-white">{{ $unreadCount }}</span>
                </a>
                <div x-data="installApp" x-cloak x-show="deferred">
                    <button type="button" x-on:click="install()" class="flex w-full items-center gap-3 px-4 py-3.5 text-left text-sm font-medium active:bg-ink-50"><x-ui.icon name="monitor-down" class="size-5 text-ink-500" /> Install the app on this phone</button>
                </div>
                <a href="{{ route('home') }}" class="flex items-center gap-3 px-4 py-3.5 text-sm font-medium active:bg-ink-50"><x-ui.icon name="eye" class="size-5 text-ink-500" /> View public site</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 px-4 py-3.5 text-left text-sm font-semibold text-brand-700 active:bg-brand-50"><x-ui.icon name="log-out" class="size-5" /> Sign out</button>
                </form>
            </div>

            @if ($navigation['planned'])
                <p class="mt-5 px-1 text-xs text-ink-500">Coming next: {{ collect($navigation['planned'])->pluck('label')->join(', ') }}.</p>
            @endif
        </div>
    </div>
</div>
