<x-layouts.app title="Reports">
    <x-ui.page-header title="Reports" description="Figures for decisions: how the kit is used, what the pipeline looks like, where time and money go." :breadcrumbs="['Reports' => null]" />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $key => [$title, $description, $icon])
            <a href="{{ route('app.reports.show', $key) }}" class="group flex gap-4 rounded-2xl border border-ink-100 bg-white p-5 shadow-card hover:border-ink-200">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-ink-950 text-white"><x-ui.icon :name="$icon" class="size-5" /></span>
                <span><span class="block font-semibold group-hover:text-brand-700">{{ $title }}</span><span class="mt-1 block text-sm text-ink-500">{{ $description }}</span></span>
            </a>
        @endforeach
    </div>
</x-layouts.app>
