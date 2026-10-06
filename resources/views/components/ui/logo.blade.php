@props(['dark' => false, 'compact' => false])
{{--
    The Nebo Stage logo (artwork: resources/brand/nebo-stage.png; traced SVGs in
    public/images/brand). dark = white logo for dark backgrounds; compact = the
    mark only. Size it with a height class; the default is h-8 (h-7 for the mark).
--}}
@php
    $file = 'images/brand/nebo-stage'.($compact ? '-mark' : '').($dark ? '-white' : '').'.svg';
    [$w, $h] = $compact ? [320, 212] : [784, 212];
    $classes = $attributes->get('class') ?: ($compact ? 'h-7' : 'h-8');
@endphp
<img src="{{ asset($file) }}" alt="{{ config('nebo.brand.name', 'Nebo Stage') }}" width="{{ $w }}" height="{{ $h }}"
     {{ $attributes->except('class')->merge(['class' => 'block w-auto shrink-0 select-none '.$classes, 'draggable' => 'false', 'decoding' => 'async']) }}>
