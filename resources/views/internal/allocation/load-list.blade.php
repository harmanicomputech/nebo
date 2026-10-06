@php
    use App\Support\Format;
    use App\Enums\LoadStatus;
    $canWork = auth()->user()->can('workLoadList', $event);
    $dispatched = $list->status === LoadStatus::Dispatched;
    $total = $list->items->count();
    $checked = $list->items->where('status', LoadStatus::Checked)->count();
@endphp
<x-layouts.app :title="'Load list '.$list->reference">
    <x-ui.page-header :title="'Load list · '.$event->name" :breadcrumbs="['Operations' => null, 'Load lists' => route('app.load-lists.index'), $list->reference => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$list->status->tone()" class="!text-sm">{{ $list->status->label() }}</x-ui.badge>
            <x-ui.button variant="secondary" icon="file-text" :href="route('app.events.load-list.print', $event)" target="_blank">Print load sheet</x-ui.button>
            @if ($canWork && ! $dispatched)
                <form method="POST" action="{{ route('app.events.load-list.sync', $event) }}">@csrf<x-ui.button type="submit" variant="secondary" icon="history">Add new allocations</x-ui.button></form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat label="Event" :value="Format::datetime($event->setup_starts_at, 'D j M')" icon="calendar-range" :hint="'Setup '.Format::datetime($event->setup_starts_at, 'g:ia')" />
        <x-ui.stat label="Venue" :value="Str::limit($event->venue, 18)" icon="route" />
        <x-ui.stat label="Lines checked" :value="$checked.' / '.$total" icon="clipboard-check" :tone="$checked === $total && $total ? 'success' : 'neutral'" />
        <x-ui.stat label="Reference" :value="$list->reference" icon="qr-code" />
    </div>

    @if ($canWork && ! $dispatched && $total)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-sm text-ink-600">Mark all:</span>
            @foreach (['picked' => 'Picked', 'loaded' => 'Loaded', 'checked' => 'Checked'] as $v => $label)
                <form method="POST" action="{{ route('app.events.load-list.advance', $event) }}">@csrf<input type="hidden" name="status" value="{{ $v }}"><x-ui.button type="submit" size="sm" variant="secondary">{{ $label }}</x-ui.button></form>
            @endforeach
            <div class="ml-auto">
                <x-ui.confirm :action="route('app.events.load-list.dispatch', $event)" variant="dark" icon="truck" title="Dispatch {{ $list->reference }}?" message="Everything on this list will be checked out to {{ $event->name }}." confirm="Dispatch" :disabled="$checked !== $total">Dispatch</x-ui.confirm>
            </div>
        </div>
    @endif
    @error('load_list')<p class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ $message }}</p>@enderror

    @if ($dispatched)
        <div class="mb-6 flex gap-3 rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm" role="note"><x-ui.icon name="truck" class="size-5 shrink-0 text-ink-600" /><p>Dispatched {{ Format::datetime($list->dispatched_at) }} by {{ $list->dispatcher?->name }}. Check equipment back in from <a class="font-semibold text-brand-700" href="{{ route('app.events.returns', $event) }}">Check-in</a>.</p></div>
    @endif

    <div class="space-y-4">
        @forelse ($groups as $name => $items)
            <x-ui.card :title="$name" :description="$items->count().' line'.($items->count() === 1 ? '' : 's')" :padding="false">
                <ul class="divide-y divide-ink-100">
                    @foreach ($items->sortBy(fn ($i) => $i->allocation->asset?->asset_tag) as $item)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <span class="min-w-0 flex-1">
                                <span class="font-mono font-semibold">{{ $item->allocation->asset?->asset_tag ?? $item->allocation->quantity.' ×' }}</span>@unless ($item->allocation->asset_id) <span class="text-sm">{{ str(app(\App\Support\Lookups::class)->label('unit', $item->allocation->equipment->unit))->lower()->plural($item->allocation->quantity) }}</span>@endunless
                                <span class="block text-xs text-ink-500">from {{ $item->allocation->location?->name ?? '—' }}@if ($item->case_label) · case {{ $item->case_label }}@endif @if ($item->checked_at) · checked by {{ $item->checker?->name }}@endif</span>
                            </span>
                            @if ($canWork && ! $dispatched)
                                <form method="POST" action="{{ route('app.events.load-list.item', [$event, $item]) }}" class="flex items-center gap-1" role="group" aria-label="Status for {{ $item->allocation->asset?->asset_tag ?? $name }}">
                                    @csrf
                                    @foreach (LoadStatus::itemStatuses() as $s)
                                        <button type="submit" name="status" value="{{ $s->value }}" @class(['rounded-md px-2.5 py-1 text-xs font-semibold', 'bg-ink-900 text-white' => $item->status === $s, 'bg-ink-100 text-ink-600 hover:bg-ink-200' => $item->status !== $s]) @if ($item->status === $s) aria-pressed="true" @endif>{{ $s->label() }}</button>
                                    @endforeach
                                </form>
                            @else
                                <x-ui.badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-ui.badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @empty
            <x-ui.card><x-ui.empty-state icon="clipboard-list" title="The load list is empty" description="Allocate equipment on the event, then add it here." /></x-ui.card>
        @endforelse
    </div>
</x-layouts.app>
