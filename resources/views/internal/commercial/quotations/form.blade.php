@php
    $editing = $quote->exists;
    $title = $editing ? 'Edit '.$quote->label() : 'New quotation';
@endphp
<x-layouts.app :title="$title">
    <x-ui.page-header :title="$title" :description="$customer ? $customer->displayName() : 'Choose the customer, then add the lines.'"
        :breadcrumbs="['Commercial' => null, 'Quotations' => route('app.quotations.index'), ($editing ? $quote->reference : 'New') => null]" />

    <form method="POST" action="{{ $editing ? route('app.quotations.update', $quote) : route('app.quotations.store') }}" class="mx-auto max-w-5xl space-y-6" data-once>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="event_request_id" value="{{ old('event_request_id', $quote->event_request_id) }}">
        <input type="hidden" name="event_id" value="{{ old('event_id', $quote->event_id) }}">

        <x-ui.card title="Quotation">
            <div class="grid gap-5 sm:grid-cols-2">
                @if ($editing)
                    <div><p class="mb-1.5 text-sm font-medium text-ink-800">Customer</p><p class="text-sm">{{ $customer->displayName() }}</p></div>
                @elseif ($customer)
                    <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                    <div><p class="mb-1.5 text-sm font-medium text-ink-800">Customer</p><p class="text-sm">{{ $customer->displayName() }}</p></div>
                @else
                    <x-ui.select label="Customer" name="customer_id" :options="$customers" placeholder="Choose a customer" required hint="Not listed? Add them on the Customers page first." />
                @endif
                <x-ui.input label="Title" name="title" :value="$quote->title" required placeholder="e.g. Annual Gala 2026 — stage, sound and lighting" />
                <x-ui.input label="Valid until" name="valid_until" type="date" :value="$quote->valid_until?->toDateString()" required />
                <x-ui.textarea label="Introduction" name="intro" :value="$quote->intro" rows="2" class="sm:col-span-2" hint="Shown above the lines, e.g. a short summary of the production." />
            </div>
        </x-ui.card>

        <x-ui.card title="Lines" description="Quantity × days × unit price. Pick from the catalogue to fill equipment day rates.">
            @include('internal.commercial._lines', ['lines' => $lines, 'sections' => $sections, 'catalogue' => $catalogue,
                'totals' => ['discount' => \App\Support\Format::nairaInput($quote->discount_kobo ?: 0), 'tax' => $quote->taxPercent()]])
        </x-ui.card>

        <x-ui.card title="Terms">
            <x-ui.textarea name="terms" :value="$quote->terms" rows="6" aria-label="Terms" />
        </x-ui.card>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="$editing ? route('app.quotations.show', $quote) : route('app.quotations.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">Save draft</x-ui.button>
        </div>
    </form>
</x-layouts.app>
