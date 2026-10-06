@props(['name', 'title' => null, 'maxWidth' => 'md'])
@php $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl']; @endphp
<div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false" x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-ink-950/60 backdrop-blur-sm" x-on:click="open = false"></div>
        {{-- A bottom sheet on phones, a dialog from sm up (D70). --}}
        <div x-show="open" x-trap.noscroll="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="max-sm:translate-y-full sm:opacity-0 sm:scale-95" x-transition:enter-end="max-sm:translate-y-0 sm:opacity-100 sm:scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="max-sm:translate-y-0 sm:opacity-100 sm:scale-100" x-transition:leave-end="max-sm:translate-y-full sm:opacity-0 sm:scale-95"
             class="relative max-h-[92vh] w-full {{ $widths[$maxWidth] ?? $widths['md'] }} overflow-y-auto overscroll-contain rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl sm:max-h-[90vh] sm:rounded-2xl sm:pb-0">
            <div class="flex justify-center pt-2.5 sm:hidden" aria-hidden="true"><span class="h-1.5 w-10 rounded-full bg-ink-200"></span></div>
            @if ($title)
                <div class="flex items-center justify-between border-b border-ink-100 px-5 py-3 sm:px-6 sm:py-4">
                    <h2 class="text-base font-semibold">{{ $title }}</h2>
                    <button type="button" class="rounded-md p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700" x-on:click="open = false" aria-label="Close">
                        <x-ui.icon name="x" class="size-5" />
                    </button>
                </div>
            @endif
            <div class="px-5 py-5 sm:px-6">{{ $slot }}</div>
        </div>
    </div>
</div>
