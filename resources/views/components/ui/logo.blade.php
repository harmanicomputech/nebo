@props(['dark' => false, 'compact' => false])
{{-- Nebo Stage mark: a stage riser under a red light beam. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg viewBox="0 0 40 40" class="size-9 shrink-0" aria-hidden="true">
        <rect width="40" height="40" rx="10" fill="#CC1F1F"/>
        <path d="M11 29V12l18 17V12" fill="none" stroke="#fff" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="7" y="31" width="26" height="2.4" rx="1.2" fill="#fff" opacity=".55"/>
    </svg>
    @unless ($compact)
        <span class="leading-none">
            <span class="font-display block text-[17px] font-bold tracking-tight {{ $dark ? 'text-white' : 'text-ink-900' }}">NEBO<span class="text-brand-600">STAGE</span></span>
            <span class="mt-0.5 block text-[9.5px] font-semibold tracking-[0.22em] uppercase {{ $dark ? 'text-ink-400' : 'text-ink-500' }}">Production&nbsp;Systems</span>
        </span>
    @endunless
</span>
