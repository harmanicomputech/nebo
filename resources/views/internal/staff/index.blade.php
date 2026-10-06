<x-layouts.app title="Staff & crew">
    <x-ui.page-header title="Staff & crew" description="People who work events. A login account is optional." :breadcrumbs="['Administration' => null, 'Staff & crew' => null]">
        <x-slot:actions>@can('create', App\Models\Staff::class)<x-ui.button icon="user-plus" :href="route('app.staff.create')">Add person</x-ui.button>@endcan</x-slot:actions>
    </x-ui.page-header>
    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-ink-100 p-4 sm:flex-row sm:items-end sm:px-6">
            <div class="relative flex-1">
                <label for="q" class="sr-only">Search</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Name, phone or email" class="field pl-9">
            </div>
            <x-ui.select name="role" :options="$roles" :value="$filters['role'] ?? ''" placeholder="All roles" class="sm:w-52" aria-label="Role" />
            <x-ui.select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$filters['status'] ?? 'active'" class="sm:w-36" aria-label="Status" />
            <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
        </form>
        @if ($staff->isEmpty())
            <x-ui.empty-state icon="contact" title="No one found" />
        @else
            <x-ui.table>
                <x-slot:head><th>Name</th><th>Role</th><th class="hidden md:table-cell">Phone</th><th class="text-right">Upcoming</th><th class="hidden sm:table-cell">Account</th></x-slot:head>
                @foreach ($staff as $person)
                    <tr>
                        <td><a href="{{ route('app.staff.show', $person) }}" class="font-semibold hover:text-brand-700">{{ $person->name }}</a></td>
                        <td class="whitespace-nowrap">{{ $person->roleLabel() }}</td>
                        <td class="hidden whitespace-nowrap text-ink-600 md:table-cell">{{ $person->phone ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $person->upcoming_count }}</td>
                        <td class="hidden sm:table-cell">@if ($person->user)<x-ui.badge tone="success" :dot="false">{{ $person->user->email }}</x-ui.badge>@else<span class="text-xs text-ink-400">None</span>@endif</td>
                    </tr>
                @endforeach
            </x-ui.table>
            @if ($staff->hasPages())<div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $staff->links() }}</div>@endif
        @endif
    </x-ui.card>
</x-layouts.app>
