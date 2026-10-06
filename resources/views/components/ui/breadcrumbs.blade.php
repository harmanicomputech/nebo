@props(['items' => []]) {{-- [label => url|null] --}}
<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="flex flex-wrap items-center gap-1 text-xs font-medium text-ink-500">
        @foreach ($items as $label => $url)
            <li class="flex items-center gap-1">
                @if (! $loop->first)<x-ui.icon name="chevron-right" class="size-3.5 text-ink-300" />@endif
                @if ($url && ! $loop->last)
                    <a href="{{ $url }}" class="hover:text-brand-700">{{ $label }}</a>
                @else
                    <span @if ($loop->last) aria-current="page" class="text-ink-700" @endif>{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
