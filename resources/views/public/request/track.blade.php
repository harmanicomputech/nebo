@php use App\Support\Format; $closed = $stage === 0; @endphp
<x-layouts.public :title="'Request '.$request->reference">
    <section class="relative isolate overflow-hidden bg-ink-950 pt-32 pb-16 text-white sm:pt-40">
        <div class="stage-grid absolute inset-0 -z-10"></div>
        <div class="stage-beam absolute inset-0 -z-10"></div>
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <p class="font-mono text-sm text-brand-500">{{ $request->reference }}</p>
            <h1 class="mt-2 text-3xl font-bold sm:text-4xl">{{ $request->event_name }}</h1>
            <p class="mt-2 text-ink-300">{{ Format::date($request->event_date) }} · {{ $request->venue }}</p>
        </div>
    </section>
    <section class="bg-ink-50 py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6">
            <div class="rounded-3xl bg-white p-6 shadow-card sm:p-8">
                @if ($closed)
                    <p class="text-lg font-semibold">This request is closed.</p>
                    <p class="mt-1 text-sm text-ink-500">If you think this is a mistake or want to plan again, please contact us or submit a new request.</p>
                @else
                    <h2 class="text-lg font-semibold">Progress</h2>
                    <ol class="mt-6 grid gap-4 sm:grid-cols-5">
                        @foreach ($stages as $n => $label)
                            <li class="flex items-center gap-3 sm:flex-col sm:items-start">
                                <span @class(['grid size-9 shrink-0 place-items-center rounded-full text-sm font-bold', 'bg-brand-600 text-white' => $n <= $stage, 'bg-ink-100 text-ink-400' => $n > $stage])>
                                    @if ($n < $stage)<x-ui.icon name="check" class="size-4" />@else{{ $n }}@endif
                                </span>
                                <span @class(['text-sm', 'font-semibold text-ink-900' => $n === $stage, 'text-ink-600' => $n < $stage, 'text-ink-400' => $n > $stage])>{{ $label }}@if ($n === $stage)<span class="sr-only"> (current)</span>@endif</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
            <div class="rounded-3xl bg-white p-6 shadow-card sm:p-8">
                <h2 class="text-lg font-semibold">Your request</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-ink-500">Submitted</dt><dd class="font-medium">{{ Format::date($request->created_at) }}</dd></div>
                    <div><dt class="text-ink-500">Event type</dt><dd class="font-medium">{{ $request->eventTypeLabel() }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-ink-500">Services</dt><dd class="font-medium">{{ $request->services->pluck('name')->push($request->services_other)->filter()->implode(', ') ?: '—' }}</dd></div>
                </dl>
            </div>
            <p class="text-center text-sm text-ink-500">Questions? Contact {{ \App\Support\Settings::string('company.name') }} at <a class="font-semibold text-brand-700" href="mailto:{{ \App\Support\Settings::string('company.email') }}">{{ \App\Support\Settings::string('company.email') }}</a> and quote your reference.</p>
        </div>
    </section>
</x-layouts.public>
