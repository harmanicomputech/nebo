@php
    $initialToasts = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'info', 'message' => session('status')],
        ['type' => 'error', 'message' => session('error')],
    ])->when($errors->any() && ! ($hideErrorToast ?? false), fn ($c) => $c->push(['type' => 'error', 'message' => $errors->count() > 1 ? 'Please fix the highlighted fields.' : $errors->first()]))
      ->filter(fn ($t) => filled($t['message']))->values();
@endphp
<div x-data="toasts(@js($initialToasts))" class="toast-stack pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:top-4 sm:right-4 sm:bottom-auto sm:left-auto sm:items-end" aria-live="polite" role="status">
    <template x-for="t in items" :key="t.id">
        <div x-transition class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border bg-white p-4 shadow-lift"
             :class="{ 'border-emerald-200': t.type === 'success', 'border-brand-200': t.type === 'error', 'border-ink-200': t.type === 'info' }">
            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-white"
                  :class="{ 'bg-emerald-600': t.type === 'success', 'bg-brand-600': t.type === 'error', 'bg-ink-800': t.type === 'info' }">
                <span x-show="t.type === 'success'"><x-ui.icon name="check" class="size-3.5" /></span>
                <span x-show="t.type === 'error'"><x-ui.icon name="circle-alert" class="size-3.5" /></span>
                <span x-show="t.type === 'info'"><x-ui.icon name="info" class="size-3.5" /></span>
            </span>
            <p class="flex-1 text-sm text-ink-800" x-text="t.message"></p>
            <button type="button" class="text-ink-400 hover:text-ink-700" x-on:click="dismiss(t.id)" aria-label="Dismiss"><x-ui.icon name="x" class="size-4" /></button>
        </div>
    </template>
</div>
