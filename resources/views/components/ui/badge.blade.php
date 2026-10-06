@props(['tone' => 'neutral', 'dot' => true]) {{-- neutral | success | warning | danger | info | brand | dark --}}
@php
    $tones = [
        'neutral' => 'bg-ink-100 text-ink-700 ring-ink-200',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'danger' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'brand' => 'bg-brand-600 text-white ring-brand-600',
        'dark' => 'bg-ink-900 text-white ring-ink-900',
    ];
    $dots = ['neutral' => 'bg-ink-400', 'success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-brand-600', 'info' => 'bg-sky-500', 'brand' => 'bg-white', 'dark' => 'bg-brand-500'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset whitespace-nowrap '.($tones[$tone] ?? $tones['neutral'])]) }}>
    @if ($dot)<span class="size-1.5 rounded-full {{ $dots[$tone] ?? $dots['neutral'] }}" aria-hidden="true"></span>@endif
    {{ $slot }}
</span>
