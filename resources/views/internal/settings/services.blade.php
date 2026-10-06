<x-layouts.app title="Services">
    <x-ui.page-header title="Settings" description="Production services offered on the public request form. Switch one off to hide it; existing requests keep it."
        :breadcrumbs="['Administration' => null, 'Settings' => auth()->user()->can('settings.view') ? route('app.settings.edit') : null, 'Services' => null]">
        <x-slot:actions><x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'service-new')">Add service</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    @include('internal.settings._tabs')

    <x-ui.card :padding="false">
        <x-ui.table>
            <x-slot:head><th>Service</th><th>On public form</th><th class="text-right">Requests</th><th><span class="sr-only">Edit</span></th></x-slot:head>
            @foreach ($services as $service)
                <tr @class(['opacity-60' => ! $service->is_active])>
                    <td><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-lg bg-ink-900 text-white"><x-ui.icon :name="$service->icon ?: 'sparkles'" class="size-4" /></span>
                        <span><span class="block font-semibold">{{ $service->name }}</span><span class="block text-xs text-ink-500">{{ $service->description }}</span></span></div></td>
                    <td>@if (! $service->is_active)<x-ui.badge>Disabled</x-ui.badge>@elseif ($service->is_public)<x-ui.badge tone="success">Shown</x-ui.badge>@else<x-ui.badge tone="info">Internal only</x-ui.badge>@endif</td>
                    <td class="text-right tabular-nums">{{ $service->requests_count }}</td>
                    <td class="text-right"><x-ui.button size="sm" variant="ghost" icon="pencil" x-data x-on:click="$dispatch('open-modal', 'service-{{ $service->id }}')">Edit</x-ui.button></td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    @foreach ($services->push(new App\Models\Service(['is_public' => true, 'is_active' => true])) as $service)
        <x-ui.modal :name="$service->exists ? 'service-'.$service->id : 'service-new'" :title="$service->exists ? 'Edit '.$service->name : 'Add service'">
            <form method="POST" action="{{ $service->exists ? route('app.settings.services.update', $service) : route('app.settings.services.store') }}" class="space-y-4" data-once>
                @csrf @if ($service->exists) @method('PUT') @endif
                @php $p = 'svc'.($service->id ?? 'new').'-'; @endphp
                <x-ui.input label="Name" name="name" :value="$service->name" required :id="$p.'name'" />
                <x-ui.textarea label="Description" name="description" :value="$service->description" rows="2" :id="$p.'desc'" />
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.input label="Icon" name="icon" :value="$service->icon" :id="$p.'icon'" hint="Lucide icon name" />
                    <x-ui.input label="Sort order" name="sort_order" type="number" min="0" :value="$service->sort_order" :id="$p.'sort'" />
                </div>
                <input type="hidden" name="is_public" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" @checked($service->is_public) class="size-4 rounded border-ink-300 text-brand-600">Show on the public request form</label>
                <input type="hidden" name="is_active" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($service->is_active) class="size-4 rounded border-ink-300 text-brand-600">Active</label>
                <x-ui.button type="submit" class="w-full">{{ $service->exists ? 'Save' : 'Add service' }}</x-ui.button>
            </form>
        </x-ui.modal>
    @endforeach
</x-layouts.app>
