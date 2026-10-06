@props(['label' => null, 'name', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'id' => null])
@php
    $id ??= 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
@endphp
<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink-800">
            {{ $label }} @if ($required)<span class="text-brand-600" aria-hidden="true">*</span>@endif
        </label>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($errorKey, $value) }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('class')->merge(['class' => 'field'.($hasError ? ' field-error' : '')]) }}>
    @if ($hasError)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-center gap-1 text-xs font-medium text-brand-700"><x-ui.icon name="circle-alert" class="size-3.5" />{{ $errors->first($errorKey) }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-ink-500">{{ $hint }}</p>
    @endif
</div>
