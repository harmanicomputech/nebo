@props(['name', 'title' => null])
<div x-data="{ open: false }" x-on:open-drawer.window="if ($event.detail === '{{ $name }}') open = true" x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-50" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink-950/50" x-on:click="open = false"></div>
        <div x-show="open" x-trap.noscroll="open"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="fixed inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-ink-100 px-6 py-4">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button type="button" class="rounded-md p-1 text-ink-400 hover:bg-ink-100" x-on:click="open = false" aria-label="Close"><x-ui.icon name="x" class="size-5" /></button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-5">{{ $slot }}</div>
        </div>
    </div>
</div>
