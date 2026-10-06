<x-layouts.public title="Track your request">
    <section class="relative isolate overflow-hidden bg-ink-950 pt-32 pb-24 text-white sm:pt-40">
        <div class="stage-grid absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-md px-4 sm:px-6">
            <h1 class="text-3xl font-bold sm:text-4xl">Track your request</h1>
            <p class="mt-3 text-ink-300">Enter the reference from your confirmation and the email you used.</p>
            <form method="POST" action="{{ route('requests.track-lookup') }}" class="mt-8 space-y-4 rounded-2xl bg-white p-6 text-ink-900" data-once>
                @csrf
                <x-ui.input label="Reference" name="reference" required placeholder="NEBO-REQ-2026-00001" />
                <x-ui.input label="Email address" name="email" type="email" required />
                <x-ui.button type="submit" class="w-full" size="lg">Find my request</x-ui.button>
            </form>
        </div>
    </section>
</x-layouts.public>
