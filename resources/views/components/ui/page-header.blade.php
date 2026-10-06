@props(['title', 'description' => null, 'breadcrumbs' => []])
<div class="mb-6 sm:mb-8">
    @if ($breadcrumbs)
        <x-ui.breadcrumbs :items="$breadcrumbs" />
    @endif
    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold text-ink-900 sm:text-3xl">{{ $title }}</h1>
            @if ($description)<p class="mt-1.5 max-w-2xl text-sm text-ink-500">{{ $description }}</p>@endif
        </div>
        @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
    </div>
</div>
