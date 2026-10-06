@props(['label', 'value', 'icon' => null, 'hint' => null, 'href' => null, 'tone' => 'neutral'])
@php
    $iconTones = ['neutral' => 'bg-ink-900 text-white', 'brand' => 'bg-brand-600 text-white', 'success' => 'bg-emerald-600 text-white', 'warning' => 'bg-amber-500 text-white'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'group relative flex min-w-0 flex-col items-start gap-3 overflow-hidden rounded-2xl border border-ink-100 bg-white p-4 shadow-card transition sm:flex-row sm:gap-4 sm:p-5'.($href ? ' hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lift' : '')]) }}>
    @if ($icon)
        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $iconTones[$tone] ?? $iconTones['neutral'] }}">
            <x-ui.icon :name="$icon" class="size-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">{{ $label }}</p>
        <p class="font-display mt-1 text-xl font-semibold break-words text-ink-900 sm:text-2xl">{{ $value }}</p>
        @if ($hint)<p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>@endif
    </div>
    @if ($href)
        <x-ui.icon name="arrow-right" class="absolute top-5 right-5 size-4 text-ink-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
    @endif
</{{ $tag }}>
