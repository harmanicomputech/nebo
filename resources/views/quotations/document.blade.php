{{--
    The quotation as the customer sees it: the internal print/PDF view and
    the public page at /q/{token} (D62). Only what we send the customer:
    lines, totals, terms, our company details. No notes, costs or staff.
--}}
@php
    use App\Enums\QuotationStatus;
    use App\Support\Format;
    use App\Support\Settings;
    $company = Settings::string('company.name');
    $canAnswer = ! $internal && $quote->status === QuotationStatus::Sent && ! $quote->isExpired();
    $statusNote = match (true) {
        $quote->status === QuotationStatus::Accepted => 'Accepted by '.$quote->responded_by_name.' on '.Format::datetime($quote->responded_at, 'j F Y'),
        $quote->status === QuotationStatus::Declined => 'Declined on '.Format::datetime($quote->responded_at, 'j F Y'),
        $quote->status === QuotationStatus::Expired || $quote->isExpired() => 'This quotation has expired. Contact us for an updated one.',
        default => null,
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Quotation {{ $quote->reference }} · {{ $company }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@page { size: A4; margin: 14mm; } @media print { .no-print { display: none !important; } body { background: #fff !important; } main { box-shadow: none !important; } } tr { break-inside: avoid; }</style>
</head>
<body class="bg-ink-100 text-ink-900">
    <div class="no-print sticky top-0 z-10 flex items-center justify-between gap-3 bg-ink-950 px-4 py-3 text-white sm:px-6">
        <p class="min-w-0 truncate text-sm">Quotation {{ $quote->label() }}</p>
        <button type="button" onclick="window.print()" class="shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold active:scale-[0.97]">{{ $internal ? 'Print / save PDF' : 'Download PDF' }}</button>
    </div>

    @if (session('success'))
        <div class="no-print mx-auto mt-4 max-w-[190mm] rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('success') }}</div>
    @endif

    <main class="mx-auto my-4 max-w-[190mm] bg-white p-5 shadow-sm sm:my-6 sm:p-10 print:my-0 print:p-0">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b-4 border-brand-600 pb-5">
            <div>
                <x-ui.logo />
                <div class="mt-3 text-xs leading-relaxed text-ink-500">
                    @if (Settings::string('company.address'))<p>{{ Settings::string('company.address') }}</p>@endif
                    <p>{{ collect([Settings::string('company.phone'), Settings::string('company.email')])->filter()->implode(' · ') }}</p>
                    <p>{{ Settings::string('company.coverage') }}</p>
                </div>
            </div>
            <div class="text-right text-sm">
                <p class="text-xs font-semibold tracking-[0.2em] text-ink-500 uppercase">Quotation</p>
                <p class="font-mono text-lg font-bold">{{ $quote->reference }}</p>
                @if ($quote->revision > 1)<p class="text-xs text-ink-500">Revision {{ $quote->revision }}</p>@endif
                <p class="mt-1">Date: {{ Format::date($quote->issued_on ?? $quote->created_at) }}</p>
                <p>Valid until: <strong>{{ Format::date($quote->valid_until) }}</strong></p>
            </div>
        </header>

        <section class="mt-6 grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">Prepared for</p>
                <p class="mt-1 font-semibold">{{ $quote->customer->company ?: $quote->customer->name }}</p>
                @if ($quote->customer->company)<p>{{ $quote->customer->name }}</p>@endif
                @if ($quote->customer->address)<p class="text-ink-600">{{ $quote->customer->address }}</p>@endif
            </div>
            <div class="sm:text-right">
                <p class="text-xs font-semibold tracking-wider text-ink-500 uppercase">Production</p>
                <p class="mt-1 font-semibold">{{ $quote->title }}</p>
            </div>
        </section>

        @if ($statusNote)
            <p class="mt-6 rounded-lg bg-ink-50 px-4 py-3 text-sm font-medium">{{ $statusNote }}</p>
        @endif

        @if ($quote->intro)<p class="mt-6 text-sm whitespace-pre-line text-ink-700">{{ $quote->intro }}</p>@endif

        <div class="mt-6">@include('internal.commercial.quotations._lines-table', ['quote' => $quote])</div>

        @if ($quote->terms)
            <section class="mt-8 border-t border-ink-100 pt-5 text-xs leading-relaxed text-ink-600">
                <p class="mb-1 font-semibold tracking-wider text-ink-500 uppercase">Terms</p>
                <p class="whitespace-pre-line">{{ $quote->terms }}</p>
            </section>
        @endif
        <p class="mt-8 hidden text-center text-xs text-ink-400 print:block">{{ $company }} · {{ $quote->reference }}</p>
    </main>

    @if ($canAnswer)
        <section class="no-print mx-auto mb-10 max-w-[190mm] rounded-2xl bg-white p-5 shadow-sm sm:p-8" x-data="{ answer: '{{ old('answer', 'accepted') }}' }" aria-labelledby="respond-heading">
            <h2 id="respond-heading" class="text-lg font-semibold">Your answer</h2>
            <p class="mt-1 text-sm text-ink-500">Accept to confirm the production at {{ Format::naira($quote->total_kobo) }}, or let us know it isn't right.</p>
            <form method="POST" action="{{ route('quotations.public.respond', $quote->public_token) }}" class="mt-5 space-y-4">
                @csrf
                <fieldset class="grid gap-2 sm:grid-cols-2"><legend class="sr-only">Answer</legend>
                    <label class="flex items-center gap-3 rounded-xl border p-3 text-sm font-semibold" :class="answer === 'accepted' ? 'border-brand-600 bg-brand-50' : 'border-ink-200'"><input type="radio" name="answer" value="accepted" x-model="answer" class="text-brand-600"> Accept the quotation</label>
                    <label class="flex items-center gap-3 rounded-xl border p-3 text-sm font-semibold" :class="answer === 'declined' ? 'border-ink-900 bg-ink-50' : 'border-ink-200'"><input type="radio" name="answer" value="declined" x-model="answer" class="text-ink-900"> Decline</label>
                </fieldset>
                <x-ui.input label="Your name" name="name" required autocomplete="name" />
                <x-ui.textarea label="Message (optional)" name="note" rows="2" />
                <label x-show="answer === 'accepted'" class="flex items-start gap-2 text-sm"><input type="checkbox" name="agree" value="1" class="mt-0.5 size-4 rounded border-ink-300 text-brand-600"> I accept this quotation and its terms on behalf of {{ $quote->customer->company ?: $quote->customer->name }}.</label>
                @error('agree')<p class="text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                @error('status')<p class="text-xs font-medium text-brand-700">{{ $message }}</p>@enderror
                <x-ui.button type="submit" class="w-full sm:w-auto" icon="send"><span x-text="answer === 'accepted' ? 'Accept quotation' : 'Send answer'">Send answer</span></x-ui.button>
            </form>
        </section>
    @endif
</body>
</html>
