@props(['label', 'value', 'icon' => null, 'hint' => null, 'href' => null, 'tone' => 'neutral'])
@php
    $iconTones = ['neutral' => 'bg-ink-900 text-white', 'brand' => 'bg-brand-600 text-white', 'success' => 'bg-emerald-600 text-white', 'warning' => 'bg-amber-500 text-white'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'group relative flex min-w-0 flex-col items-start gap-2.5 overflow-hidden rounded-2xl border border-ink-100 bg-white p-3.5 shadow-card transition sm:flex-row sm:gap-4 sm:p-5'.($href ? ' hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lift' : '')]) }}>
    @if ($icon)
        <span class="grid size-9 shrink-0 place-items-center rounded-xl sm:size-11 {{ $iconTones[$tone] ?? $iconTones['neutral'] }}">
            <x-ui.icon :name="$icon" class="size-[18px] sm:size-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-[11px] leading-tight font-semibold tracking-wider text-ink-500 uppercase sm:text-xs">{{ $label }}</p>
        <p class="font-display mt-1 text-lg font-semibold break-words text-ink-900 sm:text-2xl">{{ $value }}</p>
        @if ($hint)<p class="mt-1 text-xs text-ink-500">{{ $hint }}</p>@endif
    </div>
    @if ($href)
        <x-ui.icon name="arrow-right" class="absolute top-3.5 right-3.5 size-4 sm:top-5 sm:right-5 text-ink-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" />
    @endif
</{{ $tag }}>
