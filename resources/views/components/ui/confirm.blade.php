{{--
    A button that opens a confirmation dialog before submitting a form.
    <x-ui.confirm :action="route(...)" method="DELETE" title="Delete role?" message="..." confirm="Delete" variant="danger">Delete</x-ui.confirm>
--}}
@props(['action', 'method' => 'POST', 'title', 'message' => null, 'confirm' => 'Confirm', 'variant' => 'danger', 'icon' => null, 'size' => 'md', 'fields' => []])
<div x-data="{ open: false }" class="inline-block">
    <x-ui.button :variant="$variant" :size="$size" :icon="$icon" x-on:click="open = true" {{ $attributes }}>{{ $slot }}</x-ui.button>

    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 text-left sm:items-center" role="alertdialog" aria-modal="true" aria-label="{{ $title }}" x-on:keydown.escape.window="open = false">
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink-950/60 backdrop-blur-sm" x-on:click="open = false"></div>
        <form x-show="open" x-trap.noscroll="open" x-transition method="POST" action="{{ $action }}" data-once
              class="relative w-full overflow-hidden rounded-2xl bg-white shadow-2xl sm:max-w-md">
            @csrf
            @if (strtoupper($method) !== 'POST') @method($method) @endif
            @foreach ($fields as $fieldName => $fieldValue)
                <input type="hidden" name="{{ $fieldName }}" value="{{ $fieldValue }}">
            @endforeach
            <div class="flex gap-4 px-6 pt-6">
                <span class="grid size-11 shrink-0 place-items-center rounded-full {{ $variant === 'danger' ? 'bg-brand-50 text-brand-600' : 'bg-ink-100 text-ink-700' }}">
                    <x-ui.icon name="triangle-alert" class="size-5" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-ink-900">{{ $title }}</h2>
                    @if ($message)<p class="mt-1 text-sm text-ink-500">{{ $message }}</p>@endif
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2 bg-ink-50 px-6 py-4">
                <x-ui.button variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                <x-ui.button type="submit" :variant="$variant === 'danger' ? 'primary' : $variant">{{ $confirm }}</x-ui.button>
            </div>
        </form>
    </div>
</div>
