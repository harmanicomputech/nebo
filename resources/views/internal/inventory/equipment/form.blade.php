@php
    use App\Support\Format;
    $editing = $equipment->exists;
    $mode = old('tracking_mode', $equipment->tracking_mode?->value ?? 'serialized');
    $canCosts = auth()->user()->can('inventory.costs');
@endphp
<x-layouts.app :title="$editing ? 'Edit '.$equipment->name : 'Add equipment'">
    <x-ui.page-header :title="$editing ? 'Edit '.$equipment->name : 'Add equipment'"
        description="A catalogue item is a type of equipment. Serialized items get individual units with asset tags; quantity items are counted per location."
        :breadcrumbs="['Inventory' => null, 'Equipment' => route('app.inventory.equipment.index'), ($editing ? $equipment->name : 'Add') => null]" />

    <form method="POST" action="{{ $editing ? route('app.inventory.equipment.update', $equipment) : route('app.inventory.equipment.store') }}" enctype="multipart/form-data"
          class="grid grid-cols-1 gap-6 xl:grid-cols-3" data-once x-data="{ mode: @js($mode) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 xl:col-span-2">
            <x-ui.card title="Details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Name" name="name" :value="$equipment->name" required class="sm:col-span-2" placeholder="e.g. Robe MegaPointe" />
                    <x-ui.select label="Category" name="category_id" :options="$categories" :value="$equipment->category_id" placeholder="Choose a category" required />
                    <x-ui.input label="SKU / catalogue code" name="sku" :value="$equipment->sku" required placeholder="e.g. ML-MEGAPOINTE" hint="Unique code for this item." />
                    <x-ui.input label="Manufacturer" name="manufacturer" :value="$equipment->manufacturer" />
                    <x-ui.input label="Model" name="model" :value="$equipment->model" />
                    <x-ui.textarea label="Description" name="description" :value="$equipment->description" rows="3" class="sm:col-span-2" />
                </div>
            </x-ui.card>

            <x-ui.card title="Tracking" description="{{ $locked ? 'Tracking can\'t change because this item already has units or stock.' : 'Choose how this equipment is counted.' }}">
                <fieldset class="grid gap-3 sm:grid-cols-2" @disabled($locked)>
                    <legend class="sr-only">Tracking mode</legend>
                    @foreach (App\Enums\TrackingMode::cases() as $option)
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-ink-200 p-4 transition has-checked:border-brand-600 has-checked:bg-brand-50/40 {{ $locked ? 'cursor-not-allowed opacity-70' : 'hover:border-ink-300' }}">
                            <input type="radio" name="tracking_mode" value="{{ $option->value }}" x-model="mode" class="mt-0.5 size-4 border-ink-300 text-brand-600" @checked($mode === $option->value)>
                            <span><span class="block text-sm font-semibold text-ink-900">{{ $option->label() }}</span><span class="mt-0.5 block text-xs text-ink-500">{{ $option->description() }}</span></span>
                        </label>
                    @endforeach
                </fieldset>
                @if ($locked)<input type="hidden" name="tracking_mode" value="{{ $mode }}">@endif
                @error('tracking_mode')<p class="mt-2 text-xs font-medium text-brand-700">{{ $message }}</p>@enderror

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <x-ui.select label="Unit" name="unit" :options="$units" :value="$equipment->unit" required />
                    <div x-show="mode === 'serialized'">
                        <x-ui.input label="Asset tag prefix" name="asset_prefix" :value="$equipment->asset_prefix" placeholder="e.g. ML" hint="Units get tags like ML-001." x-bind:disabled="mode !== 'serialized'" />
                    </div>
                    <x-ui.input label="Low-stock alert below" name="low_stock_threshold" type="number" min="0" :value="$equipment->low_stock_threshold" hint="Units available. Leave blank for no alert." />
                </div>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Image">
                <div x-data="{ preview: null, drag: false }" class="space-y-3">
                    <label for="f-image" x-on:dragover.prevent="drag = true" x-on:dragleave.prevent="drag = false"
                           x-on:drop.prevent="drag = false; $refs.file.files = $event.dataTransfer.files; $refs.file.dispatchEvent(new Event('change'))"
                           :class="drag ? 'border-brand-600 bg-brand-50/50' : 'border-ink-200 hover:border-ink-300'"
                           class="relative grid aspect-[4/3] cursor-pointer place-items-center overflow-hidden rounded-xl border-2 border-dashed bg-ink-50 text-center transition">
                        <template x-if="preview"><img :src="preview" alt="" class="absolute inset-0 size-full object-cover"></template>
                        @if ($equipment->image_path)
                            <img x-show="!preview" src="{{ route('app.inventory.equipment.image', $equipment) }}" alt="" class="absolute inset-0 size-full object-cover">
                        @else
                            <span x-show="!preview" class="px-4 text-sm text-ink-500"><x-ui.icon name="download" class="mx-auto mb-2 size-6 text-ink-400" />Drop an image or <span class="font-semibold text-brand-700">browse</span><span class="mt-1 block text-xs">JPG, PNG or WebP, up to 5 MB</span></span>
                        @endif
                    </label>
                    <input id="f-image" x-ref="file" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only"
                           x-on:change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null">
                    @error('image')<p class="text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                    @if ($equipment->image_path)
                        <label class="flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" name="remove_image" value="1" class="size-4 rounded border-ink-300 text-brand-600">Remove current image</label>
                    @endif
                </div>
            </x-ui.card>

            @if ($canCosts)
                <x-ui.card title="Value & pricing">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input label="Replacement value per unit (₦)" name="replacement_value" inputmode="decimal" :value="Format::nairaInput($equipment->replacement_value_kobo)" placeholder="0" />
                        <x-ui.input label="Day rate per unit (₦)" name="day_rate" inputmode="decimal" :value="Format::nairaInput($equipment->day_rate_kobo)" placeholder="0" hint="Fills equipment lines on quotations." />
                    </div>
                </x-ui.card>
            @endif

            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" :href="$editing ? route('app.inventory.equipment.show', $equipment) : route('app.inventory.equipment.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $editing ? 'Save changes' : 'Add equipment' }}</x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
