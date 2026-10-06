@php $canManage = auth()->user()->can('settings.manage'); @endphp
<x-layouts.app title="Option lists">
    <x-ui.page-header title="Settings" description="Option lists used across the system. Rename, reorder or switch off options; records keep the option they were saved with."
        :breadcrumbs="['Administration' => null, 'Settings' => route('app.settings.edit'), 'Option lists' => null]" />
    @include('internal.settings._tabs')

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        <nav class="space-y-1" aria-label="Lists">
            @foreach ($groups as $key => $label)
                <a href="{{ route('app.settings.options', $key) }}" @if ($group === $key) aria-current="page" @endif
                   @class(['block rounded-lg px-3 py-2 text-sm font-medium', 'bg-ink-900 text-white' => $group === $key, 'text-ink-700 hover:bg-ink-100' => $group !== $key])>{{ $label }}</a>
            @endforeach
        </nav>

        <x-ui.card :title="$groups[$group]" :padding="false" class="lg:col-span-3">
            <ul class="divide-y divide-ink-100">
                @foreach ($items as $item)
                    <li class="px-5 py-3">
                        <form method="POST" action="{{ route('app.settings.options.update', $item) }}" class="flex flex-wrap items-end gap-3" data-once>
                            @csrf @method('PUT')
                            <fieldset @disabled(! $canManage) class="contents">
                                <x-ui.input label="Label" name="label" :value="$item->label" required :id="'opt'.$item->id" class="min-w-48 flex-1" />
                                <x-ui.input label="Order" name="sort_order" type="number" min="0" :value="$item->sort_order" :id="'opt-sort'.$item->id" class="w-24" />
                                <input type="hidden" name="is_active" value="0">
                                <label class="flex items-center gap-2 pb-2.5 text-sm"><input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="size-4 rounded border-ink-300 text-brand-600">Active</label>
                                @if ($canManage)<x-ui.button type="submit" variant="secondary" size="sm" class="mb-1.5">Save</x-ui.button>@endif
                            </fieldset>
                        </form>
                        <p class="mt-1 text-xs text-ink-400">Key <code>{{ $item->key }}</code>@if ($item->meta) · {{ collect($item->meta)->map(fn ($v, $k) => str_replace('_', ' ', $k).($v === true ? '' : ': '.$v))->implode(', ') }}@endif</p>
                    </li>
                @endforeach
            </ul>
            @if ($canManage)
                <form method="POST" action="{{ route('app.settings.options.store', $group) }}" class="flex flex-wrap items-end gap-3 border-t border-ink-100 bg-ink-50/60 px-5 py-4" data-once>
                    @csrf
                    <x-ui.input label="New option" name="label" required id="opt-new" class="min-w-48 flex-1" />
                    <x-ui.button type="submit" icon="plus" class="mb-0.5">Add</x-ui.button>
                </form>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
