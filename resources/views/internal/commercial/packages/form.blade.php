@php $editing = $package->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$package->name : 'New package'">
    <x-ui.page-header :title="$editing ? 'Edit '.$package->name : 'New package'" :breadcrumbs="['Commercial' => null, 'Packages' => route('app.packages.index'), ($editing ? $package->name : 'New') => null]" />
    <form method="POST" action="{{ $editing ? route('app.packages.update', $package) : route('app.packages.store') }}" class="mx-auto max-w-5xl space-y-6" data-once>
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-ui.card title="Package">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Name" name="name" :value="$package->name" required placeholder="e.g. Conference AV — 500 guests" />
                <x-ui.select label="Suits" name="event_type" :options="$eventTypes" :value="$package->event_type" placeholder="Any event" />
                <x-ui.textarea label="Description" name="description" :value="$package->description" rows="2" class="sm:col-span-2" />
                <x-ui.input label="Display order" name="sort_order" type="number" min="0" :value="$package->sort_order ?? 0" />
                <label class="flex items-center gap-2 self-end pb-2.5 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $package->is_active)) class="size-4 rounded border-ink-300 text-brand-600"> Offer for new quotations</label>
            </div>
        </x-ui.card>
        <x-ui.card title="Lines" description="List prices before VAT. Quotations copy these lines and can change them.">
            @include('internal.commercial._lines', ['lines' => $lines, 'sections' => $sections, 'catalogue' => $catalogue])
        </x-ui.card>
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="route('app.packages.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save package</x-ui.button>
        </div>
    </form>
</x-layouts.app>
