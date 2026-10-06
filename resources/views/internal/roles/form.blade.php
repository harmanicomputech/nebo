@php
    use App\Support\Permissions\PermissionCatalog;
    $editing = $role->exists;
    $isSuper = $role->name === PermissionCatalog::SUPER_ADMIN;
    $canManage = auth()->user()->can($editing ? 'update' : 'create', $editing ? $role : App\Models\Role::class) && ! $isSuper;
    $held = auth()->user()->isSuperAdmin() ? PermissionCatalog::all() : auth()->user()->getAllPermissions()->pluck('name')->all();
    $checked = old('permissions', $granted);
@endphp
<x-layouts.app :title="$editing ? $role->name : 'New role'">
    <x-ui.page-header :title="$editing ? $role->name : 'New role'"
        :description="$isSuper ? 'Super administrators automatically have every permission, including ones added in future releases.' : 'Choose exactly what people with this role can see and do.'"
        :breadcrumbs="['Administration' => null, 'Roles & permissions' => route('app.roles.index'), ($editing ? $role->name : 'New role') => null]">
        @if ($editing && $role->is_system)
            <x-slot:actions><x-ui.badge tone="neutral" :dot="false">System role</x-ui.badge></x-slot:actions>
        @endif
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('app.roles.update', $role) : route('app.roles.store') }}" data-once
          x-data="{ toggleModule(el, on) { el.closest('[data-module]').querySelectorAll('input[type=checkbox]:not(:disabled)').forEach(c => c.checked = on) } }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card title="Details" class="mb-6">
            <fieldset @disabled(! $canManage) class="grid gap-5 md:grid-cols-2">
                <x-ui.input label="Role name" name="name" :value="$role->name" required :readonly="$role->is_system" :hint="$role->is_system ? 'System roles cannot be renamed.' : null" />
                <x-ui.input label="Description" name="description" :value="$role->description" />
            </fieldset>
        </x-ui.card>

        @error('permissions')<p class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ $message }}</p>@enderror

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($modules as $key => $module)
                <section data-module class="rounded-2xl border border-ink-100 bg-white shadow-card">
                    <header class="flex items-center justify-between gap-3 border-b border-ink-100 px-5 py-3">
                        <h2 class="text-sm font-semibold text-ink-900">{{ $module['label'] }}</h2>
                        @if ($canManage)
                            <div class="flex gap-1 text-xs">
                                <button type="button" class="rounded px-2 py-1 font-semibold text-ink-600 hover:bg-ink-100" x-on:click="toggleModule($el, true)">All</button>
                                <button type="button" class="rounded px-2 py-1 font-semibold text-ink-600 hover:bg-ink-100" x-on:click="toggleModule($el, false)">None</button>
                            </div>
                        @endif
                    </header>
                    <ul class="divide-y divide-ink-50 px-2 py-1">
                        @foreach ($module['permissions'] as $permission => $label)
                            @php $locked = ! $canManage || ! in_array($permission, $held, true); @endphp
                            <li>
                                <label class="flex items-start gap-3 rounded-lg px-3 py-2.5 {{ $locked ? 'cursor-not-allowed' : 'cursor-pointer hover:bg-ink-50' }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission }}" class="mt-0.5 size-4 rounded border-ink-300 text-brand-600 focus:ring-brand-600"
                                           @checked(in_array($permission, $checked, true)) @disabled($locked)>
                                    @if ($locked && $canManage && in_array($permission, $checked, true))
                                        <input type="hidden" name="permissions[]" value="{{ $permission }}">
                                    @endif
                                    <span class="min-w-0">
                                        <span class="block text-sm text-ink-800">{{ $label }}</span>
                                        <code class="text-[11px] text-ink-400">{{ $permission }}</code>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        @if ($canManage)
            <div class="sticky bottom-0 -mx-4 mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-ink-100 bg-white/90 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                <p class="text-xs text-ink-500">You can only grant or remove permissions you hold yourself.</p>
                <div class="flex gap-2">
                    <x-ui.button variant="secondary" :href="route('app.roles.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check">{{ $editing ? 'Save role' : 'Create role' }}</x-ui.button>
                </div>
            </div>
        @endif
    </form>

    @if ($editing && ! $role->is_system)
        @can('delete', $role)
            <x-ui.card title="Delete role" description="Only possible when no users have this role." class="mt-6">
                <x-ui.confirm :action="route('app.roles.destroy', $role)" method="DELETE" icon="trash-2" title="Delete {{ $role->name }}?"
                    message="This cannot be undone. The deletion is recorded in the audit log." confirm="Delete role" :disabled="$role->users_count > 0">Delete role</x-ui.confirm>
            </x-ui.card>
        @endcan
    @endif
</x-layouts.app>
