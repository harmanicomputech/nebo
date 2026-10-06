<x-layouts.app title="Statuses">
    <x-ui.page-header title="Inventory setup" description="Equipment statuses. Rename any status; add your own. System statuses keep their behaviour because allocation, dashboards and reports depend on it."
        :breadcrumbs="['Inventory' => null, 'Setup' => null, 'Statuses' => null]">
        <x-slot:actions><x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'status-new')">Add status</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    @include('internal.inventory.setup._tabs')
    @error('status')<p class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ $message }}</p>@enderror

    <x-ui.card :padding="false">
        <x-ui.table>
            <x-slot:head><th>Status</th><th>Counts as</th><th>Allocatable</th><th>Set by</th><th class="text-right">Assets</th><th><span class="sr-only">Edit</span></th></x-slot:head>
            @foreach ($statuses as $status)
                <tr @class(['opacity-60' => ! $status->is_active])>
                    <td><x-ui.badge :tone="$status->tone">{{ $status->label }}</x-ui.badge>@if ($status->description)<span class="mt-1 block text-xs text-ink-500">{{ $status->description }}</span>@endif</td>
                    <td class="whitespace-nowrap text-ink-600">{{ $status->group->label() }}</td>
                    <td>{{ $status->is_allocatable ? 'Yes' : 'No' }}</td>
                    <td class="whitespace-nowrap">@if ($status->is_manual) Staff @else <span class="text-ink-600">Allocation engine</span>@endif @if ($status->is_system)<x-ui.badge :dot="false" class="ml-1">System</x-ui.badge>@endif</td>
                    <td class="text-right tabular-nums">{{ $status->assets_count }}</td>
                    <td class="text-right"><x-ui.button size="sm" variant="ghost" icon="pencil" x-data x-on:click="$dispatch('open-modal', 'status-{{ $status->id }}')">Edit</x-ui.button></td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @foreach ($statuses as $status)
        <x-ui.modal :name="'status-'.$status->id" :title="'Edit '.$status->label">
            <form method="POST" action="{{ route('app.inventory.setup.statuses.update', $status) }}" class="space-y-4" data-once>
                @csrf @method('PUT')
                <x-ui.input label="Label" name="label" :value="$status->label" required :id="'st'.$status->id.'-label'" />
                <x-ui.input label="Description" name="description" :value="$status->description" :id="'st'.$status->id.'-desc'" />
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.select label="Colour" name="tone" :options="$tones" :value="$status->tone" :id="'st'.$status->id.'-tone'" />
                    <x-ui.input label="Sort order" name="sort_order" type="number" min="0" :value="$status->sort_order" :id="'st'.$status->id.'-sort'" />
                </div>
                @unless ($status->is_system)
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($status->is_active) class="size-4 rounded border-ink-300 text-brand-600">Active</label>
                @endunless
                <x-ui.button type="submit" class="w-full">Save</x-ui.button>
            </form>
        </x-ui.modal>
    @endforeach

    <x-ui.modal name="status-new" title="Add status">
        <form method="POST" action="{{ route('app.inventory.setup.statuses.store') }}" class="space-y-4" data-once x-data="{ group: 'unavailable' }">
            @csrf
            <x-ui.input label="Label" name="label" required id="st-new-label" />
            <x-ui.select label="Counts as" name="group" :options="$groups" value="unavailable" x-model="group" hint="Decides how dashboards and reports treat it." />
            <label x-show="group === 'available'" class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_allocatable" value="1" class="size-4 rounded border-ink-300 text-brand-600">Units with this status can be allocated</label>
            <x-ui.select label="Colour" name="tone" :options="$tones" value="neutral" id="st-new-tone" />
            <x-ui.input label="Description" name="description" id="st-new-desc" />
            <x-ui.button type="submit" class="w-full">Add status</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
