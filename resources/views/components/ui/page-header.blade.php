@props(['title', 'description' => null, 'breadcrumbs' => []])
{{-- On phones the top bar carries the back arrow, so breadcrumbs are desktop-only and actions fill the width (D70). --}}
<div class="mb-5 sm:mb-8">
    @if ($breadcrumbs)
        <x-ui.breadcrumbs :items="$breadcrumbs" class="hidden lg:block" />
    @endif
    <div class="flex flex-wrap items-end justify-between gap-3 sm:gap-4 lg:mt-2">
        <div class="min-w-0">
            <h1 class="text-[1.625rem] leading-tight font-semibold text-ink-900 sm:text-3xl">{{ $title }}</h1>
            @if ($description)<p class="mt-1 max-w-2xl text-sm text-ink-500 sm:mt-1.5">{{ $description }}</p>@endif
        </div>
        @isset($actions)<div class="flex w-full flex-wrap items-center gap-2 sm:w-auto max-sm:[&>a]:flex-1 max-sm:[&>button]:flex-1">{{ $actions }}</div>@endisset
    </div>
</div>
