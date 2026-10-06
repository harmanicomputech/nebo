@php
    use App\Support\Format;
    use App\Support\Permissions\PermissionCatalog;
    $editing = $user->exists;
    $canEdit = $editing ? auth()->user()->can('update', $user) : true;
    $assignable ??= $roles->pluck('id')->all();
    $current = old('roles', $editing ? $user->roles->pluck('id')->all() : []);
@endphp
<x-layouts.app :title="$editing ? $user->name : 'Add user'">
    <x-ui.page-header :title="$editing ? $user->name : 'Add user'"
        :description="$editing ? $user->email : 'The new user receives an in-app welcome and should change their password at first sign-in.'"
        :breadcrumbs="['Administration' => null, 'Users' => route('app.users.index'), ($editing ? $user->name : 'Add user') => null]">
        @if ($editing)
            <x-slot:actions>
                @if ($user->is_active)<x-ui.badge tone="success">Active</x-ui.badge>@else<x-ui.badge tone="danger">Deactivated</x-ui.badge>@endif
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ $editing ? route('app.users.update', $user) : route('app.users.store') }}" class="space-y-6 xl:col-span-2" data-once>
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="roles_submitted" value="1">

            <x-ui.card title="Profile">
                <fieldset @disabled(! $canEdit) class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input label="Full name" name="name" :value="$user->name" required autocomplete="off" />
                    <x-ui.input label="Email address" name="email" type="email" :value="$user->email" required autocomplete="off" />
                    <x-ui.input label="Phone" name="phone" type="tel" :value="$user->phone" placeholder="+234 803 000 0000" />
                    <x-ui.input label="Job title" name="job_title" :value="$user->job_title" placeholder="e.g. Lighting Technician" />
                    @unless ($editing)
                        <x-ui.input label="Temporary password" name="password" type="password" required autocomplete="new-password" hint="Share it securely. They can change it from their profile." />
                        <x-ui.input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
                    @endunless
                </fieldset>
            </x-ui.card>

            <x-ui.card title="Roles" description="Roles decide what this person can see and do. You can only assign roles whose permissions you hold.">
                @error('roles')<p class="mb-4 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-800" role="alert">{{ $message }}</p>@enderror
                <fieldset @disabled(! $canEdit) class="grid gap-3 sm:grid-cols-2">
                    <legend class="sr-only">Roles</legend>
                    @foreach ($roles as $role)
                        @php $locked = ! in_array($role->id, $assignable, true); @endphp
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-ink-200 p-4 transition has-checked:border-brand-600 has-checked:bg-brand-50/40 {{ $locked ? 'cursor-not-allowed opacity-60' : 'hover:border-ink-300' }}">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="mt-0.5 size-4 rounded border-ink-300 text-brand-600 focus:ring-brand-600"
                                   @checked(in_array($role->id, array_map('intval', $current), true)) @disabled($locked)>
                            @if ($locked && in_array($role->id, array_map('intval', $current), true))
                                <input type="hidden" name="roles[]" value="{{ $role->id }}">
                            @endif
                            <span>
                                <span class="flex items-center gap-2 text-sm font-semibold text-ink-900">{{ $role->name }}
                                    @if ($role->name === PermissionCatalog::SUPER_ADMIN)<x-ui.badge tone="brand" :dot="false">All access</x-ui.badge>@endif
                                </span>
                                <span class="mt-0.5 block text-xs text-ink-500">{{ $role->description }}</span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
            </x-ui.card>

            @if ($canEdit)
                <div class="flex justify-end gap-2">
                    <x-ui.button variant="secondary" :href="route('app.users.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check">{{ $editing ? 'Save changes' : 'Create user' }}</x-ui.button>
                </div>
            @endif
        </form>

        @if ($editing)
            <div class="space-y-6">
                <x-ui.card title="Account">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-500">Created</dt><dd class="text-right">{{ Format::datetime($user->created_at) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-500">Last sign-in</dt><dd class="text-right">{{ $user->last_login_at ? Format::datetime($user->last_login_at) : 'Never' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-500">Last IP</dt><dd class="font-mono text-xs">{{ $user->last_login_ip ?? '—' }}</dd></div>
                    </dl>
                    @can('audit.view')
                        <x-ui.button variant="ghost" size="sm" class="mt-4 -ml-3" icon="history" :href="route('app.audit.index', ['type' => 'User', 'q' => $user->id])">View audit history</x-ui.button>
                    @endcan
                </x-ui.card>

                @if ($canEdit && ! auth()->user()->is($user))
                    <x-ui.card title="Reset password" description="Sets a new password for this user. Their other sessions stay signed in until they next sign in elsewhere.">
                        <form method="POST" action="{{ route('app.users.password', $user) }}" class="space-y-4" data-once>
                            @csrf @method('PUT')
                            <x-ui.input label="New password" name="password" type="password" required autocomplete="new-password" />
                            <x-ui.input label="Confirm" name="password_confirmation" type="password" required autocomplete="new-password" />
                            <x-ui.button type="submit" variant="dark" icon="key-round">Reset password</x-ui.button>
                        </form>
                    </x-ui.card>
                @endif

                @can('deactivate', $user)
                    <x-ui.card title="{{ $user->is_active ? 'Deactivate account' : 'Reactivate account' }}"
                        description="{{ $user->is_active ? 'Blocks sign-in immediately and signs them out. Their history is kept.' : 'Lets this person sign in again with their existing password.' }}">
                        @if ($user->is_active)
                            <x-ui.confirm :action="route('app.users.status', $user)" method="PUT" :fields="['active' => 0]" icon="user-x"
                                title="Deactivate {{ $user->name }}?" message="They will be signed out straight away and cannot sign in until reactivated." confirm="Deactivate">Deactivate</x-ui.confirm>
                        @else
                            <x-ui.confirm :action="route('app.users.status', $user)" method="PUT" :fields="['active' => 1]" variant="dark" icon="user-check"
                                title="Reactivate {{ $user->name }}?" message="They will be able to sign in again." confirm="Reactivate">Reactivate</x-ui.confirm>
                        @endif
                    </x-ui.card>
                @endcan
            </div>
        @endif
    </div>
</x-layouts.app>
