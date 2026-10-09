{{--
    Phone top bar (D70): logo on top-level screens, a back arrow on detail
    screens (history back when possible, else the section's list), the page
    title, search and notifications. Hidden from lg up, where the desktop
    header and sidebar take over.
--}}
<header class="sticky top-0 z-30 bg-ink-950 pt-[env(safe-area-inset-top)] text-white shadow-[0_1px_0_rgb(255_255_255/0.06)] select-none lg:hidden">
    <div class="flex h-14 items-center gap-1 px-2">
        @if ($mobile['isRoot'])
            {{-- Top-level screens show their name as the page's large title, so the bar carries the brand. --}}
            <a href="{{ route('app.dashboard') }}" class="flex min-w-0 flex-1 items-center px-2" aria-label="Dashboard"><x-ui.logo dark class="h-7" /></a>
        @else
            <a href="{{ $mobile['back'] }}" data-back class="grid size-11 shrink-0 place-items-center rounded-full text-white active:bg-white/10" aria-label="Back"><x-ui.icon name="arrow-left" class="size-6" /></a>
            <p class="min-w-0 flex-1 truncate px-1 text-[17px] font-semibold">{{ $title ?? 'Nebo Stage' }}</p>
        @endif
        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full text-white/80 active:bg-white/10" x-on:click="search = true; $nextTick(() => document.getElementById('mobile-search')?.focus())" aria-label="Search"><x-ui.icon name="search" class="size-[22px]" /></button>
        <a href="{{ route('app.notifications.index') }}" class="relative grid size-11 shrink-0 place-items-center rounded-full text-white/80 active:bg-white/10" aria-label="Notifications ({{ $unreadCount }} unread)">
            <x-ui.icon name="bell" class="size-[22px]" />
            <span data-unread-badge @if (! $unreadCount) hidden @endif class="absolute top-1.5 right-1.5 grid min-w-[18px] place-items-center rounded-full bg-brand-600 px-1 text-[10px] leading-[18px] font-bold text-white ring-2 ring-ink-950">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        </a>
    </div>
</header>

{{-- Full-screen search (phones) --}}
<div x-cloak x-show="search" x-transition.opacity.duration.150ms class="fixed inset-0 z-[55] flex flex-col bg-white lg:hidden" role="dialog" aria-modal="true" aria-label="Search">
    <form action="{{ route('app.search') }}" method="GET" role="search" class="flex flex-1 flex-col overflow-hidden" x-data="globalSearch('{{ route('app.search') }}')">
        <div class="flex items-center gap-2 border-b border-ink-100 px-3 pt-[calc(env(safe-area-inset-top)+0.5rem)] pb-2">
            <div class="relative flex-1">
                <label for="mobile-search" class="sr-only">Search</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-ink-400" />
                <input id="mobile-search" name="q" type="search" autocomplete="off" enterkeyhint="search" placeholder="Equipment, events, customers…"
                       x-model="q" x-on:input="onInput()"
                       class="w-full rounded-full border-0 bg-ink-100 py-2.5 pr-4 pl-10 text-base placeholder:text-ink-500 focus:ring-2 focus:ring-brand-600 focus:outline-none">
            </div>
            <button type="button" class="px-2 py-2 text-[15px] font-semibold text-brand-700" x-on:click="search = false; q = ''; groups = []">Cancel</button>
        </div>
        <div class="flex-1 overflow-y-auto overscroll-contain pb-[env(safe-area-inset-bottom)]">
            <template x-if="q.trim().length < 2">
                <p class="px-6 py-10 text-center text-sm text-ink-500">Search equipment, assets, events, requests, customers and people.</p>
            </template>
            <template x-if="q.trim().length >= 2 && loading && !groups.length">
                <div class="space-y-3 p-5"><div class="skeleton h-5 w-2/3"></div><div class="skeleton h-5 w-1/2"></div><div class="skeleton h-5 w-3/5"></div></div>
            </template>
            <template x-if="q.trim().length >= 2 && !loading && !groups.length">
                <p class="px-6 py-10 text-center text-sm text-ink-500">No results for “<span x-text="q"></span>”.</p>
            </template>
            <template x-for="group in groups" :key="group.label">
                <div class="pt-3">
                    <p class="px-5 pb-1 text-[11px] font-semibold tracking-wider text-ink-500 uppercase" x-text="group.label"></p>
                    <template x-for="r in group.results" :key="r.url">
                        <a :href="r.url" class="flex items-center gap-3 border-b border-ink-100 px-5 py-3 active:bg-ink-50">
                            <span class="min-w-0 flex-1"><span class="block truncate text-[15px] font-medium text-ink-900" x-text="r.title"></span>
                                <span class="block truncate text-xs text-ink-500" x-text="r.subtitle"></span></span>
                            <x-ui.icon name="chevron-right" class="size-4 shrink-0 text-ink-300" />
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </form>
</div>
