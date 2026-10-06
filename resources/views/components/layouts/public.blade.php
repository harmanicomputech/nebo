@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    @include('partials.head', ['title' => $title])
    <meta name="description" content="{{ $description ?? 'Nebo Stage — nationwide event production and technical services in Nigeria: staging, rigging, lighting, LED screens, sound, video and livestreaming.' }}">
</head>
<body class="bg-white">
<header class="absolute inset-x-0 top-0 z-30" x-data="{ open: false }">
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" aria-label="{{ config('nebo.brand.name') }} home"><x-ui.logo dark /></a>
        <nav class="hidden items-center gap-8 text-sm font-medium text-ink-200 md:flex" aria-label="Main">
            <a href="{{ route('home') }}#services" class="hover:text-white">Services</a>
            <a href="{{ route('home') }}#how" class="hover:text-white">How it works</a>
            <a href="{{ route('requests.track-form') }}" class="hover:text-white">Track a request</a>
            <a href="{{ route('requests.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 font-semibold text-white hover:bg-brand-700">Request a production</a>
        </nav>
        <button type="button" class="rounded-lg p-2 text-white md:hidden" x-on:click="open = !open" :aria-expanded="open" aria-label="Menu"><x-ui.icon name="menu" /></button>
    </div>
    <div x-cloak x-show="open" x-transition class="mx-4 rounded-xl bg-ink-900 p-2 md:hidden">
        <a href="{{ route('home') }}#services" x-on:click="open = false" class="block rounded-lg px-4 py-3 text-sm text-white hover:bg-white/5">Services</a>
        <a href="{{ route('home') }}#how" x-on:click="open = false" class="block rounded-lg px-4 py-3 text-sm text-white hover:bg-white/5">How it works</a>
        <a href="{{ route('requests.track-form') }}" class="block rounded-lg px-4 py-3 text-sm text-white hover:bg-white/5">Track a request</a>
        <a href="{{ route('requests.create') }}" class="block rounded-lg bg-brand-600 px-4 py-3 text-sm font-semibold text-white">Request a production</a>
    </div>
</header>

<main>{{ $slot }}</main>

<footer class="bg-ink-950 text-white/60">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-10 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <x-ui.logo dark />
        <p class="text-sm">&copy; {{ date('Y') }} {{ config('nebo.brand.name') }}. Event production &amp; technical services — nationwide.</p>
        <a href="{{ route('login') }}" class="text-xs text-white/60 hover:text-white">Staff sign in</a>
    </div>
</footer>
@include('partials.toasts')
</body>
</html>
