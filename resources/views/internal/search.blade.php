<x-layouts.app title="Search">
    <x-ui.page-header title="Search" :description="$term ? 'Results for “'.$term.'”' : 'Search across the modules you have access to.'" :breadcrumbs="['Search' => null]" />

    @if (mb_strlen($term) < App\Support\Search\GlobalSearch::MIN_LENGTH)
        <x-ui.card><x-ui.empty-state icon="search" title="Type at least two characters" description="Use the search box at the top of every page." /></x-ui.card>
    @elseif ($groups === [])
        <x-ui.card><x-ui.empty-state icon="search" title="No results" description="Nothing you have access to matches “{{ $term }}”." /></x-ui.card>
    @else
        <div class="space-y-6">
            @foreach ($groups as $group)
                <x-ui.card :title="$group['label']" :padding="false">
                    <ul class="divide-y divide-ink-100">
                        @foreach ($group['results'] as $r)
                            <li><a href="{{ $r['url'] }}" class="flex items-center gap-3 px-6 py-3 hover:bg-ink-50">
                                <x-ui.icon :name="$group['icon']" class="size-4 text-ink-400" />
                                <span class="min-w-0"><span class="block text-sm font-medium text-ink-900">{{ $r['title'] }}</span><span class="block truncate text-xs text-ink-500">{{ $r['subtitle'] }}</span></span>
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.app>
