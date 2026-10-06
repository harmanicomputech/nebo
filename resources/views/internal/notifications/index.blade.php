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
