<div x-data="{ edit: false }" class="relative shrink-0">
    @if ($category->trashed())
        <form method="POST" action="{{ route('app.inventory.setup.categories.restore', $category->id) }}">@csrf<x-ui.button type="submit" size="sm" variant="ghost">Restore</x-ui.button></form>
    @else
        <x-ui.button size="sm" variant="ghost" icon="pencil" x-on:click="edit = true" aria-label="Edit {{ $category->name }}" />
        <div x-cloak x-show="edit" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-label="Edit {{ $category->name }}" x-on:keydown.escape.window="edit = false">
            <div class="fixed inset-0 bg-ink-950/60" x-on:click="edit = false"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" x-trap.noscroll="edit">
                <h2 class="mb-4 text-base font-semibold">Edit {{ $category->name }}</h2>
                <form method="POST" action="{{ route('app.inventory.setup.categories.update', $category) }}" class="space-y-4" data-once>
                    @csrf @method('PUT')
                    <x-ui.input label="Name" name="name" :value="$category->name" required :id="'cat-name-'.$category->id" />
                    <input type="hidden" name="parent_id" value="{{ $category->parent_id }}">
                    <x-ui.input label="Icon" name="icon" :value="$category->icon" :id="'cat-icon-'.$category->id" />
                    <x-ui.input label="Sort order" name="sort_order" type="number" min="0" :value="$category->sort_order" :id="'cat-sort-'.$category->id" />
                    <div class="flex justify-between gap-2">
                        <x-ui.button variant="secondary" x-on:click="edit = false">Cancel</x-ui.button>
                        <x-ui.button type="submit">Save</x-ui.button>
                    </div>
                </form>
                <form method="POST" action="{{ route('app.inventory.setup.categories.destroy', $category) }}" class="mt-4 border-t border-ink-100 pt-4" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Archive '.$category->name.'? Existing equipment keeps it.') }})">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-brand-700 hover:underline">Archive category</button>
                </form>
            </div>
        </div>
    @endif
</div>
