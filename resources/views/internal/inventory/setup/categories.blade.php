<x-layouts.app title="Categories">
    <x-ui.page-header title="Inventory setup" description="Categories organise the catalogue. Archiving a category hides it from new equipment; existing equipment keeps it."
        :breadcrumbs="['Inventory' => null, 'Setup' => null, 'Categories' => null]">
        <x-slot:actions><x-ui.button icon="plus" x-data x-on:click="$dispatch('open-modal', 'category-new')">Add category</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    @include('internal.inventory.setup._tabs')
    @error('category')<p class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ $message }}</p>@enderror

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($categories as $category)
            <x-ui.card :padding="false" @class(['opacity-60' => $category->trashed()])>
                <div class="flex items-start gap-3 px-5 py-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-ink-900 text-white"><x-ui.icon :name="$category->icon ?: 'package'" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2"><h2 class="font-semibold text-ink-900">{{ $category->name }}</h2>@if ($category->trashed())<x-ui.badge :dot="false">Archived</x-ui.badge>@endif</div>
                        <p class="text-xs text-ink-500">{{ $category->equipment_count }} {{ Str::plural('item', $category->equipment_count) }}</p>
                    </div>
                    @include('internal.inventory.setup._category-actions', ['category' => $category])
                </div>
                @if ($category->children->isNotEmpty())
                    <ul class="border-t border-ink-100 px-5 py-2">
                        @foreach ($category->children as $child)
                            <li @class(['flex items-center gap-2 py-1.5 text-sm', 'opacity-60' => $child->trashed()])>
                                <x-ui.icon name="chevron-right" class="size-3.5 text-ink-300" />
                                <span class="flex-1">{{ $child->name }} <span class="text-xs text-ink-400">({{ $child->equipment_count }})</span>@if ($child->trashed()) <x-ui.badge :dot="false">Archived</x-ui.badge>@endif</span>
                                @include('internal.inventory.setup._category-actions', ['category' => $child])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.modal name="category-new" title="Add category">
        <form method="POST" action="{{ route('app.inventory.setup.categories.store') }}" class="space-y-4" data-once>
            @csrf
            <x-ui.input label="Name" name="name" required />
            <x-ui.select label="Parent" name="parent_id" :options="$parents" placeholder="None (top-level category)" hint="Pick a parent to create a subcategory." />
            <x-ui.input label="Icon" name="icon" placeholder="e.g. lightbulb" hint="A Lucide icon name (optional)." />
            <x-ui.input label="Sort order" name="sort_order" type="number" min="0" />
            <x-ui.button type="submit" class="w-full">Add category</x-ui.button>
        </form>
    </x-ui.modal>
    @if ($errors->any() && ! $errors->has('category'))<div x-data x-init="$nextTick(() => $dispatch('open-modal', 'category-new'))"></div>@endif
</x-layouts.app>
