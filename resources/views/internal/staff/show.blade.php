@php use App\Support\Format; @endphp
<x-layouts.app :title="$staff->name">
    <x-ui.page-header :title="$staff->name" :description="$staff->roleLabel()" :breadcrumbs="['Administration' => null, 'Staff & crew' => auth()->user()->can('staff.view') ? route('app.staff.index') : null, $staff->name => null]">
        <x-slot:actions>
            @unless ($staff->is_active)<x-ui.badge>Inactive</x-ui.badge>@endunless
            @can('update', $staff)<x-ui.button variant="secondary" icon="pencil" :href="route('app.staff.edit', $staff)">Edit</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-ui.card title="Contact">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-ink-500">Phone</dt><dd>@if ($staff->phone)<a class="text-brand-700" href="tel:{{ $staff->phone }}">{{ $staff->phone }}</a>@else — @endif</dd></div>
                <div><dt class="text-ink-500">Email</dt><dd>{{ $staff->email ?? '—' }}</dd></div>
                <div><dt class="text-ink-500">Login account</dt><dd>{{ $staff->user?->email ?? 'None' }}</dd></div>
            </dl>
            @if ($staff->notes)<p class="mt-4 border-t border-ink-100 pt-3 text-sm text-ink-600">{{ $staff->notes }}</p>@endif
        </x-ui.card>
        <x-ui.card title="Upcoming events" :padding="false" class="lg:col-span-2">
            @forelse ($upcoming as $event)
                <a href="{{ route('app.events.show', $event) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-ink-100 px-5 py-3 last:border-0 hover:bg-ink-50">
                    <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $event->name }}</span><span class="block text-xs text-ink-500">{{ Format::datetime($event->setup_starts_at, 'D j M, g:ia') }} → {{ Format::datetime($event->breakdown_ends_at, 'D j M') }} · {{ $event->venue }}</span></span>
                    <span class="text-xs text-ink-600">{{ $event->team->first()?->roleLabel() ?? 'Production manager' }}</span>
                    <x-ui.badge :tone="$event->status->tone()">{{ $event->status->label() }}</x-ui.badge>
                </a>
            @empty
                <x-ui.empty-state icon="calendar-range" title="Nothing booked" />
            @endforelse
            @if ($past->isNotEmpty())
                <div class="border-t border-ink-100 px-5 py-4">
                    <p class="mb-2 text-xs font-semibold tracking-wider text-ink-500 uppercase">Recent</p>
                    <ul class="space-y-1 text-sm">@foreach ($past as $event)<li><a href="{{ route('app.events.show', $event) }}" class="hover:text-brand-700">{{ $event->name }}</a> <span class="text-xs text-ink-500">· {{ Format::date($event->starts_at) }}</span></li>@endforeach</ul>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
