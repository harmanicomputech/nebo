@props(['label' => null, 'name', 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false, 'id' => null])
@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $current = (string) old($name, $value);
    $hasError = $errors->has($name);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink-800">{{ $label }} @if ($required)<span class="text-brand-600" aria-hidden="true">*</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except('class')->merge(['class' => 'field pr-9'.($hasError ? ' field-error' : '')]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hasError)
        <p class="mt-1.5 text-xs font-medium text-brand-700">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-ink-500">{{ $hint }}</p>
    @endif
</div>
