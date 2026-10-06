@props(['name', 'title' => null, 'maxWidth' => 'md'])
@php $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl']; @endphp
<div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false" x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink-950/60 backdrop-blur-sm" x-on:click="open = false"></div>
        <div x-show="open" x-trap.noscroll="open" x-transition
             class="relative w-full {{ $widths[$maxWidth] ?? $widths['md'] }} overflow-hidden rounded-2xl bg-white shadow-2xl">
            @if ($title)
                <div class="flex items-center justify-between border-b border-ink-100 px-6 py-4">
                    <h2 class="text-base font-semibold">{{ $title }}</h2>
                    <button type="button" class="rounded-md p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700" x-on:click="open = false" aria-label="Close">
                        <x-ui.icon name="x" class="size-5" />
                    </button>
                </div>
            @endif
            <div class="px-6 py-5">{{ $slot }}</div>
        </div>
    </div>
</div>
