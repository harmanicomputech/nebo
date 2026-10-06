@props([
    'variant' => 'primary', // primary | secondary | ghost | danger | dark
    'size' => 'md',         // sm | md | lg
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'button',
])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60 whitespace-nowrap';
    $sizes = ['sm' => 'px-3 py-1.5 text-xs', 'md' => 'px-4 py-2.5 text-sm', 'lg' => 'px-6 py-3.5 text-base'];
    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus-visible:outline-brand-600',
        'dark' => 'bg-ink-900 text-white shadow-sm hover:bg-ink-800 focus-visible:outline-ink-900',
        'secondary' => 'border border-ink-200 bg-white text-ink-800 shadow-xs hover:border-ink-300 hover:bg-ink-50 focus-visible:outline-ink-400',
        'ghost' => 'text-ink-600 hover:bg-ink-100 hover:text-ink-900',
        'danger' => 'border border-brand-200 bg-white text-brand-700 hover:bg-brand-50 focus-visible:outline-brand-600',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
    $iconClass = $size === 'sm' ? 'size-3.5' : 'size-4';
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" :class="$iconClass" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
        @if ($iconRight)<x-ui.icon :name="$iconRight" :class="$iconClass" />@endif
    </button>
@endif
