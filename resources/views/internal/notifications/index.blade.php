<x-layouts.app title="Notifications">
    <x-ui.page-header title="Notifications" :breadcrumbs="['Notifications' => null]">
        <x-slot:actions>
            <div class="inline-flex rounded-lg border border-ink-200 bg-white p-0.5 text-sm" role="tablist">
                <a href="{{ route('app.notifications.index') }}" role="tab" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-ink-900 text-white' => $filter === 'all', 'text-ink-600 hover:text-ink-900' => $filter !== 'all']) @if ($filter === 'all') aria-selected="true" @endif>All</a>
                <a href="{{ route('app.notifications.index', ['filter' => 'unread']) }}" role="tab" @class(['rounded-md px-3 py-1.5 font-medium', 'bg-ink-900 text-white' => $filter === 'unread', 'text-ink-600 hover:text-ink-900' => $filter !== 'unread']) @if ($filter === 'unread') aria-selected="true" @endif>Unread</a>
            </div>
            <form method="POST" action="{{ route('app.notifications.read-all') }}">@csrf<x-ui.button type="submit" variant="secondary" icon="check">Mark all read</x-ui.button></form>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Pop-ups on this device (D75). --}}
    <div x-data="pushToggle" class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-ink-100 bg-white p-4 shadow-card">
        <span class="grid size-10 shrink-0 place-items-center rounded-xl" :class="state === 'on' ? 'bg-emerald-600 text-white' : 'bg-ink-900 text-white'"><x-ui.icon name="bell-ring" class="size-5" /></span>
        <div class="min-w-0 flex-1 text-sm">
            <p class="font-semibold text-ink-900">Pop-up notifications on this device</p>
            <p class="text-ink-500" x-show="state === 'on'">On. Every new notification pops up here, even when Nebo Stage is closed.</p>
            <p class="text-ink-500" x-show="state === 'off'">Off. Turn on to get a pop-up for every new notification.</p>
            <p class="text-ink-500" x-show="state === 'denied'">Blocked in this browser. Allow notifications for this site in the browser or phone settings, then reload.</p>
            <p class="text-ink-500" x-show="state === 'ios-install'">On iPhone: tap Share → Add to Home Screen, open Nebo Stage from the home screen, then turn them on here.</p>
            <p class="text-ink-500" x-show="state === 'unsupported'">This browser can't show pop-ups when the app is closed. New notifications still pop up while it is open.</p>
        </div>
        <x-ui.button size="sm" icon="bell" x-cloak x-show="state === 'off'" x-on:click="enable()" ::disabled="busy" class="max-sm:w-full">Turn on</x-ui.button>
        <x-ui.button size="sm" variant="secondary" icon="bell-off" x-cloak x-show="state === 'on'" x-on:click="disable()" ::disabled="busy" class="max-sm:w-full">Turn off on this device</x-ui.button>
    </div>

    <x-ui.card :padding="false">
        @forelse ($notifications as $n)
            @php $tones = ['success' => 'bg-emerald-50 text-emerald-700', 'warning' => 'bg-amber-50 text-amber-700', 'danger' => 'bg-brand-50 text-brand-700', 'info' => 'bg-ink-100 text-ink-700']; @endphp
            <a href="{{ route('app.notifications.open', $n->id) }}" class="flex gap-4 border-b border-ink-100 px-5 py-4 last:border-0 hover:bg-ink-50 sm:px-6 {{ $n->read_at ? '' : 'bg-brand-50/30' }}">
                <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $tones[$n->data['level'] ?? 'info'] ?? $tones['info'] }}"><x-ui.icon :name="$n->data['icon'] ?? 'bell'" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-ink-900">{{ $n->data['title'] ?? 'Notification' }}</span>
                        @unless ($n->read_at)<span class="size-2 rounded-full bg-brand-600" aria-label="Unread"></span>@endunless
                    </span>
                    <span class="mt-0.5 block text-sm text-ink-600">{{ $n->data['body'] ?? '' }}</span>
                </span>
                <time class="shrink-0 text-xs text-ink-400" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->diffForHumans() }}</time>
            </a>
        @empty
            <x-ui.empty-state icon="bell" title="{{ $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' }}" description="Booking requests, conflicts, maintenance alerts and account changes will appear here." />
        @endforelse
        @if ($notifications->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $notifications->links() }}</div>@endif
    </x-ui.card>
</x-layouts.app>
