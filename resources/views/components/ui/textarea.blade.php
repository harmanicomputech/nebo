@props(['label' => null, 'name', 'value' => null, 'hint' => null, 'required' => false, 'rows' => 4, 'id' => null])
@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $hasError = $errors->has($name);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink-800">{{ $label }} @if ($required)<span class="text-brand-600" aria-hidden="true">*</span>@endif</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('class')->merge(['class' => 'field'.($hasError ? ' field-error' : '')]) }}>{{ old($name, $value) }}</textarea>
    @if ($hasError)
        <p class="mt-1.5 text-xs font-medium text-brand-700">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-ink-500">{{ $hint }}</p>
    @endif
</div>
