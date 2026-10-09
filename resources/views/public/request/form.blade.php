@php
    $tz = config('nebo.display_timezone');
    // Old datetime values come back in UTC (see PublicEventRequest); show them in Lagos time.
    $local = fn ($v) => filled($v) && strtotime($v) ? \Carbon\Carbon::parse($v, 'UTC')->setTimezone($tz)->format('Y-m-d\TH:i') : '';
    $oldServices = array_map('strval', (array) old('services', []));
    $sections = ['event' => 'Your event', 'services' => 'Services', 'requirements' => 'Requirements', 'logistics' => 'Logistics', 'budget' => 'Budget', 'contact' => 'Contact'];
@endphp
<x-layouts.public title="Request a Production" description="Request event production or equipment rental from Nebo Stage: stages, truss and roof systems, LED screens, rigging, lighting, sound and crew, anywhere in Nigeria.">
    <section class="relative isolate overflow-hidden bg-ink-950 pt-32 pb-16 text-white sm:pt-40 sm:pb-24">
        <div class="stage-grid absolute inset-0 -z-10"></div>
        <div class="stage-beam absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold tracking-[0.3em] text-brand-400 uppercase">Production &amp; Equipment Request</p>
            <h1 class="mt-4 text-4xl leading-tight font-bold sm:text-6xl">LET’S BUILD YOUR STAGE</h1>
            <p class="mt-5 max-w-2xl text-lg text-ink-300">Tell us about your event and the stage, screens, equipment and crew you need. Our team will review your request and send you a tailored production and equipment rental quotation.</p>
        </div>
    </section>

    <section class="bg-ink-50 py-10 sm:py-16">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[220px_1fr] lg:px-8">
            {{-- Progress rail --}}
            <nav class="hidden lg:block" aria-label="Form sections">
                <ol class="sticky top-8 space-y-1" x-data="{ active: 'event' }" x-init="
                    const io = new IntersectionObserver((entries) => entries.forEach(e => { if (e.isIntersecting) active = e.target.id }), { rootMargin: '-40% 0px -55% 0px' });
                    document.querySelectorAll('[data-section]').forEach(s => io.observe(s));">
                    @foreach ($sections as $id => $label)
                        <li><a href="#{{ $id }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition" :class="active === '{{ $id }}' ? 'bg-white text-ink-900 shadow-card' : 'text-ink-500 hover:text-ink-900'">
                            <span class="grid size-6 place-items-center rounded-full text-xs font-bold" :class="active === '{{ $id }}' ? 'bg-brand-600 text-white' : 'bg-ink-200 text-ink-600'">{{ $loop->iteration }}</span>{{ $label }}
                        </a></li>
                    @endforeach
                </ol>
            </nav>

            <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="space-y-6" data-once novalidate
                  x-data="{ type: @js(old('event_type', '')), services: @js($oldServices), design: @js(old('has_existing_design') ? 'yes' : (old('has_existing_design') === false ? 'no' : '')), files: [], drag: false,
                            addFiles(list) { const dt = new DataTransfer(); [...this.$refs.files.files, ...list].slice(0, {{ App\Services\Documents\UploadRules::MAX_FILES }}).forEach(f => dt.items.add(f)); this.$refs.files.files = dt.files; this.files = [...dt.files]; } }">
                @csrf
                <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
                <input type="hidden" name="form_started" value="{{ $formStarted }}">
                <div class="absolute -left-[9999px]" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                @if ($errors->any())
                    <div class="rounded-2xl border border-brand-200 bg-white p-5 shadow-card" role="alert">
                        <p class="flex items-center gap-2 font-semibold text-brand-700"><x-ui.icon name="circle-alert" class="size-5" />Please check {{ $errors->count() === 1 ? 'one detail' : $errors->count().' details' }} below</p>
                        @error('form')<p class="mt-1 text-sm text-ink-600">{{ $message }}</p>@enderror
                    </div>
                @endif

                {{-- 1. Event --}}
                <section id="event" data-section class="scroll-mt-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
                    <header class="mb-6"><p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">Step 1</p><h2 class="mt-1 text-2xl font-semibold">Event information</h2></header>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input label="Event name" name="event_name" required class="sm:col-span-2" placeholder="e.g. Annual Leadership Conference 2026" />
                        <fieldset class="sm:col-span-2">
                            <legend class="mb-2 text-sm font-medium text-ink-800">Event type <span class="text-brand-600">*</span></legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($eventTypes as $key => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="event_type" value="{{ $key }}" x-model="type" class="peer sr-only" @checked(old('event_type') === $key)>
                                        <span class="inline-block rounded-full border border-ink-200 bg-white px-4 py-2 text-sm font-medium text-ink-700 transition peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600 hover:border-ink-400">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('event_type')<p class="mt-1.5 text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                        </fieldset>
                        <div x-show="type === 'other'" x-cloak class="sm:col-span-2"><x-ui.input label="What type of event?" name="event_type_other" x-bind:required="type === 'other'" /></div>
                        <x-ui.input label="Event date" name="event_date" type="date" required :min="now($tz)->toDateString()" />
                        <x-ui.input label="Venue & location" name="venue" required placeholder="Venue name, street, city, state" />
                        <x-ui.input label="Phone number" name="phone" type="tel" required autocomplete="tel" placeholder="0803 123 4567" />
                        <x-ui.input label="Email address" name="email" type="email" required autocomplete="email" />
                    </div>
                </section>

                {{-- 2. Services --}}
                <section id="services" data-section class="scroll-mt-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
                    <header class="mb-6"><p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">Step 2</p><h2 class="mt-1 text-2xl font-semibold">Production services required</h2><p class="mt-1 text-sm text-ink-500">Choose everything you need.</p></header>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($services as $service)
                            <label class="group relative flex cursor-pointer flex-col gap-3 rounded-2xl border border-ink-200 p-4 transition has-checked:border-brand-600 has-checked:bg-brand-50/40 has-focus-visible:outline-2 has-focus-visible:outline-brand-600 hover:border-ink-400">
                                <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="services" class="peer sr-only">
                                <span class="flex items-center justify-between">
                                    <span class="grid size-10 place-items-center rounded-xl bg-ink-950 text-white transition group-has-checked:bg-brand-600"><x-ui.icon :name="$service->icon ?: 'sparkles'" class="size-5" /></span>
                                    <span class="grid size-5 place-items-center rounded-full border border-ink-300 text-white transition group-has-checked:border-brand-600 group-has-checked:bg-brand-600"><x-ui.icon name="check" class="size-3" /></span>
                                </span>
                                <span><span class="block text-sm font-semibold text-ink-900">{{ $service->name }}</span>@if ($service->description)<span class="mt-0.5 block text-xs text-ink-500">{{ $service->description }}</span>@endif</span>
                            </label>
                        @endforeach
                        <label class="group flex cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-ink-300 p-4 transition has-checked:border-brand-600 has-checked:bg-brand-50/40">
                            <input type="checkbox" name="services[]" value="other" x-model="services" class="sr-only">
                            <span class="grid size-10 place-items-center rounded-xl bg-ink-100 text-ink-600 group-has-checked:bg-brand-600 group-has-checked:text-white"><x-ui.icon name="plus" class="size-5" /></span>
                            <span class="text-sm font-semibold text-ink-900">Something else</span>
                        </label>
                    </div>
                    @error('services')<p class="mt-2 text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                    <div x-show="services.includes('other')" x-cloak class="mt-4"><x-ui.input label="Which other service?" name="services_other" /></div>
                </section>

                {{-- 3. Requirements --}}
                <section id="requirements" data-section class="scroll-mt-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
                    <header class="mb-6"><p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">Step 3</p><h2 class="mt-1 text-2xl font-semibold">Production requirements</h2></header>
                    <x-ui.textarea label="Tell us briefly about your production requirements." name="requirements" rows="7" required
                        hint="Please include stage size, LED screen requirements, lighting, sound, number of cameras, or any specific production requirements you may have." />
                </section>

                {{-- 4. Logistics --}}
                <section id="logistics" data-section class="scroll-mt-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
                    <header class="mb-6"><p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">Step 4</p><h2 class="mt-1 text-2xl font-semibold">Event logistics</h2></header>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-ui.input label="Number of days" name="duration_days" type="number" min="1" max="60" :value="1" required />
                        <x-ui.input label="Starts (optional)" name="starts_at" type="datetime-local" :value="$local(old('starts_at'))" :use-old="false" />
                        <x-ui.input label="Ends (optional)" name="ends_at" type="datetime-local" :value="$local(old('ends_at'))" :use-old="false" />
                        <x-ui.input label="Required setup date & time" name="setup_at" type="datetime-local" required :value="$local(old('setup_at'))" :use-old="false" class="sm:col-span-3 sm:max-w-sm" hint="When our crew needs to start setting up at the venue." />
                    </div>

                    <fieldset class="mt-6">
                        <legend class="text-sm font-medium text-ink-800">Do you have an existing event design, production plan, stage layout or technical specification?</legend>
                        <div class="mt-2 flex gap-2">
                            @foreach (['yes' => 'Yes', 'no' => 'No'] as $value => $label)
                                <label class="cursor-pointer"><input type="radio" name="has_existing_design" value="{{ $value }}" x-model="design" class="peer sr-only">
                                    <span class="inline-block rounded-full border border-ink-200 px-5 py-2 text-sm font-medium peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:outline-2 peer-focus-visible:outline-brand-600">{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div x-show="design === 'yes'" x-cloak class="mt-5">
                        <label for="files" x-on:dragover.prevent="drag = true" x-on:dragleave.prevent="drag = false" x-on:drop.prevent="drag = false; addFiles($event.dataTransfer.files)"
                               :class="drag ? 'border-brand-600 bg-brand-50/60' : 'border-ink-300 hover:border-ink-400'"
                               class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed bg-ink-50/60 px-6 py-10 text-center transition">
                            <x-ui.icon name="download" class="size-8 text-ink-400" />
                            <span class="mt-3 text-sm font-semibold text-ink-900">Drop your files here or <span class="text-brand-700 underline">browse</span></span>
                            <span class="mt-1 text-xs text-ink-500">{{ $fileHint }}. Up to {{ App\Services\Documents\UploadRules::MAX_FILES }} files.</span>
                        </label>
                        <input id="files" x-ref="files" type="file" name="files[]" multiple accept="{{ $accept }}" class="sr-only" x-on:change="files = [...$event.target.files]">
                        <ul class="mt-3 space-y-2" x-show="files.length">
                            <template x-for="f in files" :key="f.name + f.size">
                                <li class="flex items-center gap-3 rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-sm"><x-ui.icon name="file-text" class="size-4 text-ink-400" /><span class="flex-1 truncate" x-text="f.name"></span><span class="text-xs text-ink-500" x-text="(f.size / 1048576).toFixed(1) + ' MB'"></span></li>
                            </template>
                        </ul>
                        @foreach ($errors->get('files*') as $messages)<p class="mt-2 text-xs font-medium text-brand-700">{{ $messages[0] }}</p>@endforeach
                    </div>
                </section>

                {{-- 5. Budget --}}
                <section id="budget" data-section class="scroll-mt-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
                    <header class="mb-6"><p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">Step 5</p><h2 class="mt-1 text-2xl font-semibold">Estimated production budget</h2></header>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($budgets as $key => $label)
                            <label class="cursor-pointer"><input type="radio" name="budget_range" value="{{ $key }}" class="peer sr-only" @checked(old('budget_range') === $key)>
                                <span class="block rounded-xl border border-ink-200 px-4 py-3 text-sm font-medium text-ink-800 transition peer-checked:border-brand-600 peer-checked:bg-brand-50/50 peer-checked:text-brand-800 peer-focus-visible:outline-2 peer-focus-visible:outline-brand-600 hover:border-ink-400">{{ $label }}</span></label>
                        @endforeach
                    </div>
                    @error('budget_range')<p class="mt-2 text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                </section>

                {{-- 6. Contact --}}
                <section id="contact" data-section class="scroll-mt-8 rounded-3xl bg-white p-6 shadow-card sm:p-8">
                    <header class="mb-6"><p class="text-xs font-semibold tracking-[0.2em] text-brand-600 uppercase">Step 6</p><h2 class="mt-1 text-2xl font-semibold">Contact information</h2></header>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input label="Contact person" name="contact_person" required autocomplete="name" />
                        <x-ui.input label="Company / organization" name="company" autocomplete="organization" />
                        <x-ui.textarea label="Is there anything else our production team should know about your event?" name="additional_info" rows="4" class="sm:col-span-2" />
                    </div>
                </section>

                <div class="flex flex-col items-start gap-4 rounded-3xl bg-ink-950 p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <p class="text-sm text-ink-300">We'll review your request and get back to you with a tailored production solution and quotation.</p>
                    <x-ui.button type="submit" size="lg" icon-right="arrow-right" class="w-full sm:w-auto">SUBMIT YOUR REQUEST</x-ui.button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.public>
