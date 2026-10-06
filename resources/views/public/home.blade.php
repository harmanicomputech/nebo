<x-layouts.public>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-ink-950 pt-32 pb-24 text-white sm:pt-40 sm:pb-32">
        <div class="stage-grid absolute inset-0 -z-10"></div>
        <div class="stage-beam absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold tracking-[0.2em] text-ink-200 uppercase">
                <span class="size-1.5 rounded-full bg-brand-500"></span>{{ $company['coverage'] }}
            </p>
            <h1 class="mt-6 max-w-4xl text-4xl leading-[1.05] font-bold sm:text-6xl lg:text-7xl">
                Production that <span class="text-brand-500">holds the stage.</span>
            </h1>
            <p class="mt-6 max-w-2xl text-lg text-ink-300">
                Staging, rigging, lighting, LED screens, sound, video and livestreaming — engineered, delivered and run by one technical team, anywhere in Nigeria.
            </p>
            <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
                <x-ui.button size="lg" :href="route('requests.create')" icon-right="arrow-right">Request a production</x-ui.button>
                <a href="{{ route('requests.track-form') }}" class="text-sm font-semibold text-ink-300 hover:text-white">Already sent a request? Track it →</a>
            </div>
        </div>
    </section>

    {{-- Services --}}
    <section id="services" class="scroll-mt-20 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold tracking-[0.25em] text-brand-600 uppercase">What we do</p>
                <h2 class="mt-3 text-3xl font-semibold sm:text-4xl">Every technical layer of your event</h2>
                <p class="mt-4 text-ink-500">From a single conference room to a festival main stage, we plan, supply, install and operate the production.</p>
            </div>
            <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <li class="group flex items-center gap-4 rounded-2xl border border-ink-100 bg-white p-5 shadow-card transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lift">
                        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-ink-950 text-white transition group-hover:bg-brand-600"><x-ui.icon :name="$service['icon'] ?: 'sparkles'" class="size-6" /></span>
                        <span><span class="block font-semibold text-ink-900">{{ $service['name'] }}</span>@if ($service['description'])<span class="block text-xs text-ink-500">{{ $service['description'] }}</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="scroll-mt-20 bg-ink-50 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-semibold sm:text-4xl">How it works</h2>
            <ol class="mt-12 grid gap-6 md:grid-cols-4">
                @foreach ([
                    ['Tell us about your event', 'Date, venue, the services you need and any stage layout or technical plan you already have.'],
                    ['We review and plan', 'Our production team assesses the requirements and, where needed, the site.'],
                    ['Receive a tailored quotation', 'A clear production solution and quotation built around your event and budget.'],
                    ['We deliver the show', 'Equipment, crew, logistics and operation — from load-in to breakdown.'],
                ] as $i => [$heading, $text])
                    <li class="rounded-2xl bg-white p-6 shadow-card">
                        <span class="font-display text-4xl font-bold text-brand-600">0{{ $i + 1 }}</span>
                        <h3 class="mt-4 font-semibold text-ink-900">{{ $heading }}</h3>
                        <p class="mt-2 text-sm text-ink-500">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Contact --}}
    <section id="contact" class="scroll-mt-20 py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-ink-950 px-6 py-12 text-white sm:px-12">
                <div class="stage-beam absolute inset-0 opacity-70"></div>
                <div class="relative flex flex-col gap-8 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-3xl font-semibold">Let's plan your event</h2>
                        <p class="mt-2 text-ink-300">Talk to the {{ $company['name'] }} production team.</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        @if ($company['email'])
                            <x-ui.button size="lg" :href="route('requests.create')" icon-right="arrow-right">Start your request</x-ui.button>
                        <x-ui.button size="lg" variant="secondary" :href="'mailto:'.$company['email']" icon="mail">{{ $company['email'] }}</x-ui.button>
                        @endif
                        @if ($company['phone'])
                            <x-ui.button size="lg" variant="secondary" :href="'tel:'.preg_replace('/[^+0-9]/', '', $company['phone'])" icon="phone">{{ $company['phone'] }}</x-ui.button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
