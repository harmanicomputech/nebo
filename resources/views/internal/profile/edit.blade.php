<x-layouts.app title="Profile">
    <x-ui.page-header title="Your profile" description="Your details and password. Your email address and roles are managed by an administrator." :breadcrumbs="['Profile' => null]" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="Details">
            <form method="POST" action="{{ route('app.profile.update') }}" class="space-y-5" data-once>
                @csrf @method('PUT')
                <div class="flex items-center gap-4">
                    <x-ui.avatar :user="$user" size="lg" />
                    <div>
                        <p class="font-semibold">{{ $user->email }}</p>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @forelse ($user->roles as $role)<x-ui.badge :dot="false">{{ $role->name }}</x-ui.badge>@empty<span class="text-xs text-ink-400">No role</span>@endforelse
                        </div>
                    </div>
                </div>
                <x-ui.input label="Full name" name="name" :value="$user->name" required />
                <x-ui.input label="Phone" name="phone" type="tel" :value="$user->phone" />
                <x-ui.input label="Job title" name="job_title" :value="$user->job_title" />
                <x-ui.button type="submit" icon="check">Save details</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Change password" description="Changing your password signs you out on your other devices.">
            <form method="POST" action="{{ route('app.profile.password') }}" class="space-y-5" data-once>
                @csrf @method('PUT')
                <x-ui.input label="Current password" name="current_password" type="password" required autocomplete="current-password" />
                <x-ui.input label="New password" name="password" type="password" required autocomplete="new-password" />
                <x-ui.input label="Confirm new password" name="password_confirmation" type="password" required autocomplete="new-password" />
                <x-ui.button type="submit" variant="dark" icon="key-round">Change password</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
