@props(['lines' => 3])
<div {{ $attributes->merge(['class' => 'space-y-3']) }} aria-hidden="true">
    @for ($i = 0; $i < $lines; $i++)
        <div class="skeleton h-4" style="width: {{ [100, 85, 70, 92, 60][$i % 5] }}%"></div>
    @endfor
</div>
