@props(['title' => 'Error'])
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
    <meta name="robots" content="noindex">
</head>
<body class="h-full bg-ink-950 text-white">
<div class="relative isolate flex min-h-full items-center justify-center overflow-hidden px-6 py-16">
    <div class="stage-grid absolute inset-0 -z-10"></div>
    <div class="stage-beam absolute inset-0 -z-10"></div>
    <div class="w-full max-w-lg text-center">
        <a href="{{ url('/') }}" class="inline-block"><x-ui.logo dark /></a>
        <div class="font-display mt-12 flex justify-center text-7xl font-bold text-brand-500 sm:text-8xl">{{ $code ?? '' }}</div>
        <h1 class="mt-6 text-2xl font-semibold sm:text-3xl">{{ $heading ?? $title }}</h1>
        <p class="mt-3 text-ink-300">{{ $slot }}</p>
        <div class="mt-10 flex flex-wrap justify-center gap-3">
            @isset($actions)
                {{ $actions }}
            @else
                <x-ui.button :href="auth()->check() ? route('app.dashboard') : url('/')" icon="arrow-left">{{ auth()->check() ? 'Back to dashboard' : 'Back to home' }}</x-ui.button>
            @endisset
        </div>
    </div>
</div>
</body>
</html>
