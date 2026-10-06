@php use App\Support\Permissions\PermissionCatalog; @endphp
<x-layouts.app title="Roles & permissions">
    <x-ui.page-header title="Roles & permissions" description="Roles group granular permissions. Assign roles to users to control what they can see and do."
        :breadcrumbs="['Administration' => null, 'Roles & permissions' => null]">
        @can('create', App\Models\Role::class)
            <x-slot:actions><x-ui.button :href="route('app.roles.create')" icon="plus">New role</x-ui.button></x-slot:actions>
        @endcan
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            @php $isSuper = $role->name === PermissionCatalog::SUPER_ADMIN; $count = $isSuper ? $totalPermissions : $role->permissions_count; @endphp
            <a href="{{ route('app.roles.edit', $role) }}" class="group flex flex-col rounded-2xl border border-ink-100 bg-white p-5 shadow-card transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lift">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid size-11 place-items-center rounded-xl {{ $isSuper ? 'bg-brand-600 text-white' : 'bg-ink-900 text-white' }}"><x-ui.icon :name="$isSuper ? 'shield' : 'shield-check'" /></span>
                    @if ($role->is_system)<x-ui.badge tone="neutral" :dot="false">System</x-ui.badge>@else<x-ui.badge tone="info" :dot="false">Custom</x-ui.badge>@endif
                </div>
                <h2 class="mt-4 text-base font-semibold text-ink-900 group-hover:text-brand-700">{{ $role->name }}</h2>
                <p class="mt-1 line-clamp-2 flex-1 text-sm text-ink-500">{{ $role->description }}</p>
                <div class="mt-4">
                    <div class="flex justify-between text-xs text-ink-500"><span>{{ $count }} of {{ $totalPermissions }} permissions</span><span>{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}</span></div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink-100"><div class="h-full rounded-full {{ $isSuper ? 'bg-brand-600' : 'bg-ink-800' }}" style="width: {{ $totalPermissions ? round($count / $totalPermissions * 100) : 0 }}%"></div></div>
                </div>
            </a>
        @endforeach
    </div>
</x-layouts.app>
