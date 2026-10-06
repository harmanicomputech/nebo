@props(['user', 'size' => 'md'])
@php $sizes = ['sm' => 'size-7 text-[10px]', 'md' => 'size-9 text-xs', 'lg' => 'size-14 text-base']; @endphp
<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-600 to-brand-800 font-semibold text-white ring-2 ring-white '.($sizes[$size] ?? $sizes['md'])]) }} aria-hidden="true">{{ $user->initials() }}</span>
