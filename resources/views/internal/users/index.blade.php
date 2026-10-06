@php use App\Support\Format; @endphp
<x-layouts.app title="Users">
    <x-ui.page-header title="Users" description="Accounts that can sign in to the operations system. Crew without logins are managed as staff (planned)."
        :breadcrumbs="['Administration' => null, 'Users' => null]">
        @can('create', App\Models\User::class)
            <x-slot:actions><x-ui.button :href="route('app.users.create')" icon="user-plus">Add user</x-ui.button></x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <form method="GET" class="flex flex-col gap-3 border-b border-ink-100 p-4 sm:flex-row sm:items-end sm:px-6">
            <div class="relative flex-1">
                <label for="q" class="sr-only">Search users</label>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" />
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, phone or job title" class="field pl-9">
            </div>
            <x-ui.select name="role" :options="$roles->pluck('name', 'id')->all()" :value="$filters['role'] ?? ''" placeholder="All roles" class="sm:w-52" aria-label="Role" />
            <x-ui.select name="status" :options="['active' => 'Active', 'inactive' => 'Deactivated']" :value="$filters['status'] ?? ''" placeholder="Any status" class="sm:w-40" aria-label="Status" />
            <div class="flex gap-2">
                <x-ui.button type="submit" variant="dark" icon="filter">Filter</x-ui.button>
                @if (array_filter($filters))<x-ui.button variant="ghost" :href="route('app.users.index')">Clear</x-ui.button>@endif
            </div>
        </form>

        @if ($users->isEmpty())
            <x-ui.empty-state icon="users" title="No users found" description="Try a different search or clear the filters." />
        @else
            <x-ui.table>
                <x-slot:head>
                    <th>User</th><th>Roles</th><th>Status</th><th>Last sign-in</th><th class="text-right"><span class="sr-only">Actions</span></th>
                </x-slot:head>
                @foreach ($users as $u)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :user="$u" />
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-ink-900">{{ $u->name }}</p>
                                    <p class="truncate text-xs text-ink-500">{{ $u->email }}@if ($u->job_title) · {{ $u->job_title }}@endif</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($u->roles as $role)
                                    <x-ui.badge :tone="$role->name === App\Support\Permissions\PermissionCatalog::SUPER_ADMIN ? 'brand' : 'neutral'" :dot="false">{{ $role->name }}</x-ui.badge>
                                @empty
                                    <span class="text-xs text-ink-400">No role</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            @if ($u->is_active)<x-ui.badge tone="success">Active</x-ui.badge>@else<x-ui.badge tone="danger">Deactivated</x-ui.badge>@endif
                        </td>
                        <td class="whitespace-nowrap text-ink-500">{{ $u->last_login_at ? Format::datetime($u->last_login_at) : 'Never' }}</td>
                        <td class="text-right">
                            <x-ui.button variant="secondary" size="sm" :href="route('app.users.edit', $u)">Open</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
            <div class="border-t border-ink-100 px-4 py-3 sm:px-6">{{ $users->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.app>
