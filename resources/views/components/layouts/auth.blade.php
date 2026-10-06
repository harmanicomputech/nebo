@props(['title' => null])
<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
    @include('partials.head', ['title' => $title])
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="h-full bg-white">
<div class="grid min-h-full lg:grid-cols-[1.1fr_1fr]">
    <div class="relative hidden overflow-hidden bg-ink-950 lg:block">
        <div class="stage-grid absolute inset-0"></div>
        <div class="stage-beam absolute inset-0"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-white">
            <x-ui.logo dark />
            <div class="max-w-md">
                <p class="text-xs font-semibold tracking-[0.25em] text-brand-400 uppercase">Operations platform</p>
                <h2 class="mt-4 text-4xl leading-tight font-semibold">Every light, every cable, every show — under control.</h2>
                <p class="mt-4 text-ink-300">Inventory, bookings, allocation, logistics and maintenance for Nebo Stage productions nationwide.</p>
            </div>
            <p class="text-xs text-ink-500">&copy; {{ date('Y') }} {{ config('nebo.brand.name') }}. Authorised personnel only.</p>
        </div>
    </div>
    <div class="flex flex-col justify-center px-6 py-12 sm:px-12">
        <div class="mx-auto w-full max-w-sm">
            <div class="lg:hidden"><x-ui.logo /></div>
            {{ $slot }}
        </div>
    </div>
</div>
@include('partials.toasts', ['hideErrorToast' => true])
</body>
</html>
