<x-layouts.public title="Request received">
    <section class="relative isolate overflow-hidden bg-ink-950 pt-32 pb-24 text-white sm:pt-40">
        <div class="stage-grid absolute inset-0 -z-10"></div>
        <div class="stage-beam absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-2xl px-4 text-center sm:px-6">
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-brand-600 shadow-[0_0_60px_rgb(204_31_31/0.5)]"><x-ui.icon name="check" class="size-8" /></span>
            <h1 class="mt-8 text-3xl font-bold sm:text-5xl">Thank you. Your request is in.</h1>
            <p class="mt-4 text-lg text-ink-300">Our production team will review your request for <strong class="text-white">{{ $request->event_name }}</strong> and contact you with a tailored production solution and quotation.</p>

            <div class="mx-auto mt-10 max-w-md rounded-2xl border border-white/10 bg-white/5 p-6 text-left backdrop-blur">
                <p class="text-xs font-semibold tracking-[0.2em] text-ink-400 uppercase">Your reference</p>
                <p class="mt-1 font-mono text-2xl font-bold tracking-wide" x-data>{{ $request->reference }}</p>
                <p class="mt-4 text-sm text-ink-300">Keep this reference. You can follow your request at any time with this private link:</p>
                <a href="{{ route('requests.track', $request->public_token) }}" class="mt-2 block truncate text-sm font-semibold text-brand-400 hover:underline">{{ route('requests.track', $request->public_token) }}</a>
            </div>

            <div class="mt-10 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.button size="lg" :href="route('requests.track', $request->public_token)" icon="history">Track this request</x-ui.button>
                <x-ui.button size="lg" variant="secondary" :href="route('home')">Back to home</x-ui.button>
            </div>
        </div>
    </section>
</x-layouts.public>
