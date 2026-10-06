<x-layouts.app title="Locations">
    <x-ui.page-header title="Inventory setup" description="Warehouses, sites and other places equipment can be. A location can be archived once it's empty."
        :breadcrumbs="['Inventory' => null, 'Setup' => null, 'Locations' => null]">
        <x-slot:actions><x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'location-new')">Add location</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    @include('internal.inventory.setup._tabs')
    @error('location')<p class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ $message }}</p>@enderror

    <x-ui.card :padding="false">
        <x-ui.table>
            <x-slot:head><th>Location</th><th>Type</th><th class="text-right">Assets</th><th class="text-right">Stock units</th><th>Status</th><th><span class="sr-only">Actions</span></th></x-slot:head>
            @foreach ($locations as $location)
                <tr @class(['opacity-60' => $location->trashed()])>
                    <td><span class="font-semibold">{{ $location->name }}</span> <span class="font-mono text-xs text-ink-400">{{ $location->code }}</span>@if ($location->address)<span class="block text-xs text-ink-500">{{ $location->address }}</span>@endif</td>
                    <td class="whitespace-nowrap">{{ $location->typeLabel() }}</td>
                    <td class="text-right tabular-nums">@if ($location->assets_count)<a class="text-brand-700 hover:underline" href="{{ route('app.inventory.assets.index', ['location' => $location->id]) }}">{{ $location->assets_count }}</a>@else 0 @endif</td>
                    <td class="text-right tabular-nums">{{ number_format((int) $location->stock_units) }}</td>
                    <td>@if ($location->trashed())<x-ui.badge>Archived</x-ui.badge>@elseif ($location->is_active)<x-ui.badge tone="success">Active</x-ui.badge>@else<x-ui.badge>Inactive</x-ui.badge>@endif</td>
                    <td class="text-right whitespace-nowrap">
                        @if ($location->trashed())
                            <form method="POST" action="{{ route('app.inventory.setup.locations.restore', $location->id) }}">@csrf<x-ui.button type="submit" size="sm" variant="ghost">Restore</x-ui.button></form>
                        @else
                            <x-ui.button size="sm" variant="ghost" icon="pencil" x-data x-on:click="$dispatch('open-modal', 'location-{{ $location->id }}')">Edit</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @foreach ($locations->whereNull('deleted_at') as $location)
        <x-ui.modal :name="'location-'.$location->id" :title="'Edit '.$location->name">
            <form method="POST" action="{{ route('app.inventory.setup.locations.update', $location) }}" class="space-y-4" data-once>
                @csrf @method('PUT')
                @include('internal.inventory.setup._location-fields', ['location' => $location])
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($location->is_active) class="size-4 rounded border-ink-300 text-brand-600">Active (can receive equipment)</label>
                <x-ui.button type="submit" class="w-full">Save</x-ui.button>
            </form>
            <div class="mt-4 border-t border-ink-100 pt-4">
                <x-ui.confirm :action="route('app.inventory.setup.locations.destroy', $location)" method="DELETE" size="sm" title="Archive {{ $location->name }}?" message="Only empty locations can be archived. History keeps referring to it." confirm="Archive">Archive location</x-ui.confirm>
            </div>
        </x-ui.modal>
    @endforeach

    <x-ui.modal name="location-new" title="Add location">
        <form method="POST" action="{{ route('app.inventory.setup.locations.store') }}" class="space-y-4" data-once>
            @csrf
            @include('internal.inventory.setup._location-fields', ['location' => new App\Models\Location])
            <x-ui.button type="submit" class="w-full">Add location</x-ui.button>
        </form>
    </x-ui.modal>
</x-layouts.app>
