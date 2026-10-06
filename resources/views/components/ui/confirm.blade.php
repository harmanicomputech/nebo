{{--
    A button that opens a confirmation dialog before submitting a form.
    <x-ui.confirm :action="route(...)" method="DELETE" title="Delete role?" message="..." confirm="Delete" variant="danger">Delete</x-ui.confirm>
--}}
@props(['action', 'method' => 'POST', 'title', 'message' => null, 'confirm' => 'Confirm', 'variant' => 'danger', 'icon' => null, 'size' => 'md', 'fields' => []])
<div x-data="{ open: false }" class="inline-block">
    <x-ui.button :variant="$variant" :size="$size" :icon="$icon" x-on:click="open = true" {{ $attributes }}>{{ $slot }}</x-ui.button>

    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center text-left sm:items-center sm:p-4" role="alertdialog" aria-modal="true" aria-label="{{ $title }}" x-on:keydown.escape.window="open = false">
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink-950/60 backdrop-blur-sm" x-on:click="open = false"></div>
        <form x-show="open" x-trap.noscroll="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="max-sm:translate-y-full sm:opacity-0 sm:scale-95" x-transition:enter-end="max-sm:translate-y-0 sm:opacity-100 sm:scale-100"
              x-transition:leave="transition ease-in duration-150" x-transition:leave-start="max-sm:translate-y-0 sm:opacity-100 sm:scale-100" x-transition:leave-end="max-sm:translate-y-full sm:opacity-0 sm:scale-95" method="POST" action="{{ $action }}" data-once
              class="relative w-full overflow-hidden rounded-t-3xl bg-white shadow-2xl sm:max-w-md sm:rounded-2xl">
            <div class="flex justify-center pt-2.5 sm:hidden" aria-hidden="true"><span class="h-1.5 w-10 rounded-full bg-ink-200"></span></div>
            @csrf
            @if (strtoupper($method) !== 'POST') @method($method) @endif
            @foreach ($fields as $fieldName => $fieldValue)
                <input type="hidden" name="{{ $fieldName }}" value="{{ $fieldValue }}">
            @endforeach
            <div class="flex gap-4 px-6 pt-4 sm:pt-6">
                <span class="grid size-11 shrink-0 place-items-center rounded-full {{ $variant === 'danger' ? 'bg-brand-50 text-brand-600' : 'bg-ink-100 text-ink-700' }}">
                    <x-ui.icon name="triangle-alert" class="size-5" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-ink-900">{{ $title }}</h2>
                    @if ($message)<p class="mt-1 text-sm text-ink-500">{{ $message }}</p>@endif
                </div>
            </div>
            <div class="mt-6 flex flex-col-reverse gap-2 bg-ink-50 px-6 py-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:flex-row sm:justify-end sm:pb-4">
                <x-ui.button variant="secondary" x-on:click="open = false" class="max-sm:w-full max-sm:py-3">Cancel</x-ui.button>
                <x-ui.button type="submit" :variant="$variant === 'danger' ? 'primary' : $variant" class="max-sm:w-full max-sm:py-3">{{ $confirm }}</x-ui.button>
            </div>
        </form>
    </div>
</div>
