<x-layouts.auth title="Reset password">
    <a href="{{ route('login') }}" class="mt-10 inline-flex items-center gap-1 text-sm font-medium text-ink-500 hover:text-ink-900 lg:mt-0"><x-ui.icon name="arrow-left" class="size-4" />Back to sign in</a>
    <h1 class="mt-6 text-2xl font-semibold text-ink-900">Reset your password</h1>
    <p class="mt-2 text-sm text-ink-500">Enter your work email and we'll send a link to choose a new password.</p>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5" data-once>
        @csrf
        <x-ui.input label="Email address" name="email" type="email" autocomplete="username" required autofocus />
        <x-ui.button type="submit" size="lg" class="w-full">Email me a reset link</x-ui.button>
    </form>
</x-layouts.auth>
