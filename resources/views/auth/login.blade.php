<x-layouts.auth title="Sign in">
    <h1 class="mt-10 text-2xl font-semibold text-ink-900 lg:mt-0">Sign in</h1>
    <p class="mt-2 text-sm text-ink-500">Use your Nebo Stage staff account.</p>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-6 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="alert">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5" data-once>
        @csrf
        <x-ui.input label="Email address" name="email" type="email" autocomplete="username" required autofocus />
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="f-password" class="text-sm font-medium text-ink-800">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-700 hover:underline">Forgot password?</a>
            </div>
            <x-ui.input name="password" type="password" autocomplete="current-password" required />
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-700">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-ink-300 text-brand-600 focus:ring-brand-600">
            Keep me signed in on this device
        </label>
        <x-ui.button type="submit" size="lg" class="w-full" icon-right="arrow-right">Sign in</x-ui.button>
    </form>

    <p class="mt-10 text-center text-xs text-ink-400">
        Looking to book a production? <a href="{{ route('home') }}" class="font-semibold text-ink-700 hover:text-brand-700">Visit the Nebo Stage site</a>
    </p>
</x-layouts.auth>
