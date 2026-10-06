<x-layouts.auth title="Choose a new password">
    <h1 class="mt-10 text-2xl font-semibold text-ink-900 lg:mt-0">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5" data-once>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-ui.input label="Email address" name="email" type="email" :value="$email" autocomplete="username" required />
        <x-ui.input label="New password" name="password" type="password" autocomplete="new-password" required />
        <x-ui.input label="Confirm new password" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-ui.button type="submit" size="lg" class="w-full">Save password</x-ui.button>
    </form>
</x-layouts.auth>
