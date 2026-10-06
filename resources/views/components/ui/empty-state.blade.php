@props(['icon' => 'inbox', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="grid size-14 place-items-center rounded-2xl bg-ink-50 text-ink-400 ring-1 ring-ink-100">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <h3 class="mt-4 text-base font-semibold text-ink-900">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-ink-500">{{ $description }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
