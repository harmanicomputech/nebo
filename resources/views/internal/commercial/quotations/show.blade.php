@php
    use App\Enums\QuotationStatus;
    use App\Support\Format;
    $u = auth()->user();
    $s = $quote->status;
@endphp
<x-layouts.app :title="$quote->reference">
    <x-ui.page-header :title="$quote->title" :description="$quote->label().' · '.$quote->customer->displayName()"
        :breadcrumbs="['Commercial' => null, 'Quotations' => route('app.quotations.index'), $quote->reference => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$quote->isExpired() ? 'warning' : $s->tone()" class="!text-sm">{{ $quote->isExpired() ? 'Expired' : $s->label() }}</x-ui.badge>
            <x-ui.button variant="secondary" icon="printer" :href="route('app.quotations.print', $quote)" target="_blank">Print / PDF</x-ui.button>
            @can('update', $quote)<x-ui.button variant="secondary" icon="pencil" :href="route('app.quotations.edit', $quote)">Edit</x-ui.button>@endcan
            @can('send', $quote)
                <x-ui.confirm :action="route('app.quotations.send', $quote)" variant="primary" icon="send" title="Approve and send {{ $quote->reference }}?" message="The customer can then view and accept it through their link{{ $quote->customer->email ? ', and it is emailed to '.$quote->customer->email.' when email is set up' : '' }}. Lines can't be changed after this without a revision." confirm="Approve and send">Approve & send</x-ui.confirm>
            @endcan
            @can('respond', $quote)<x-ui.button icon="circle-check" x-data x-on:click="$dispatch('open-modal', 'quote-respond')">Record answer</x-ui.button>@endcan
            @can('revise', $quote)
                <form method="POST" action="{{ route('app.quotations.revise', $quote) }}" data-once>@csrf<x-ui.button type="submit" variant="secondary" icon="history">Revise</x-ui.button></form>
            @endcan
            @can('create', App\Models\Quotation::class)
                <form method="POST" action="{{ route('app.quotations.duplicate', $quote) }}" data-once>@csrf<x-ui.button type="submit" variant="ghost" icon="copy">Duplicate</x-ui.button></form>
            @endcan
            @can('cancel', $quote)<x-ui.button variant="ghost" icon="ban" x-data x-on:click="$dispatch('open-modal', 'quote-cancel')">Cancel</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($s === QuotationStatus::Sent)
        <div class="mb-6 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" x-data="{ copied: false }">
            <p class="font-semibold">Customer link</p>
            <p class="mt-1">The customer can view, accept or decline here. Share it by WhatsApp or email if email isn't set up.@if ($quote->viewed_at) Opened {{ Format::datetime($quote->viewed_at, 'D j M, g:ia') }}.@else Not opened yet.@endif</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <input type="text" readonly value="{{ $publicUrl }}" class="field min-w-0 flex-1 bg-white font-mono text-xs" aria-label="Customer link" x-on:focus="$el.select()">
                <x-ui.button size="sm" variant="secondary" icon="copy" x-on:click="navigator.clipboard?.writeText($root.querySelector('input[readonly]').value); copied = true; setTimeout(() => copied = false, 2000)"><span x-text="copied ? 'Copied' : 'Copy'">Copy</span></x-ui.button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-ui.card title="Lines" :padding="false">
                @if ($packages && $s === QuotationStatus::Draft)
                    <x-slot:actions>
                        <form method="POST" action="{{ route('app.quotations.package', $quote) }}" class="flex gap-2">
                            @csrf
                            <x-ui.select name="package_id" :options="$packages" placeholder="Add a package…" aria-label="Package" />
                            <x-ui.button type="submit" size="sm" variant="secondary">Add</x-ui.button>
                        </form>
                    </x-slot:actions>
                @endif
                <div class="p-5 sm:p-6">
                    @if ($quote->intro)<p class="mb-5 text-sm whitespace-pre-line text-ink-700">{{ $quote->intro }}</p>@endif
                    @if ($quote->items->isEmpty())
                        <p class="text-sm text-ink-500">No lines yet. Edit the quotation or add a package.</p>
                    @else
                        @include('internal.commercial.quotations._lines-table', ['quote' => $quote])
                    @endif
                </div>
            </x-ui.card>
            @if ($quote->terms)
                <x-ui.card title="Terms"><p class="text-sm whitespace-pre-line text-ink-700">{{ $quote->terms }}</p></x-ui.card>
            @endif
            <x-ui.card title="Notes">
                @include('internal.partials.notes', ['notes' => $quote->notes, 'action' => $u->can('addNote', $quote) ? route('app.quotations.notes', $quote) : null])
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Details" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Customer</dt><dd class="text-right">@can('customers.view')<a href="{{ route('app.customers.show', $quote->customer) }}" class="font-medium text-brand-700 hover:underline">{{ $quote->customer->displayName() }}</a>@else{{ $quote->customer->displayName() }}@endcan</dd></div>
                    @if ($quote->request)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Request</dt><dd class="text-right"><a href="{{ route('app.requests.show', $quote->request) }}" class="font-mono text-xs text-brand-700 hover:underline">{{ $quote->request->reference }}</a></dd></div>@endif
                    @if ($quote->event)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Event</dt><dd class="text-right"><a href="{{ route('app.events.show', [$quote->event, 'tab' => 'financial']) }}" class="font-medium text-brand-700 hover:underline">{{ $quote->event->name }}</a></dd></div>@endif
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Total</dt><dd class="text-right text-lg font-semibold tabular-nums">{{ Format::naira($quote->total_kobo) }}</dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Valid until</dt><dd @class(['text-right font-medium', 'text-brand-700' => $quote->isExpired()])>{{ Format::date($quote->valid_until) }}</dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Prepared by</dt><dd class="text-right">{{ $quote->preparer?->name ?? '—' }}</dd></div>
                    @if ($quote->sent_at)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Sent</dt><dd class="text-right">{{ Format::datetime($quote->sent_at, 'j M Y, g:ia') }}<span class="block text-xs text-ink-500">by {{ $quote->sender?->name }}</span></dd></div>@endif
                    @if ($quote->responded_at)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">{{ $s === QuotationStatus::Accepted ? 'Accepted' : 'Answered' }}</dt><dd class="text-right">{{ Format::datetime($quote->responded_at, 'j M Y, g:ia') }}<span class="block text-xs text-ink-500">by {{ $quote->responded_by_name }}</span></dd></div>@endif
                </dl>
                @if ($quote->response_note)<p class="border-t border-ink-100 px-5 py-4 text-sm text-ink-600">“{{ $quote->response_note }}”</p>@endif
            </x-ui.card>
            <x-ui.card title="Documents" description="Signed copies, purchase orders and payment proofs.">
                @include('internal.partials.documents', ['documents' => $quote->documents, 'uploadUrl' => $u->can('documents.manage') && $u->can('quotations.manage') ? route('app.documents.store', ['quotation', $quote->id]) : null])
            </x-ui.card>
            <x-ui.card title="Timeline">
                @include('internal.partials.timeline', ['changes' => $quote->statusChanges, 'labels' => fn ($v) => QuotationStatus::tryFrom($v)?->label() ?? $v])
            </x-ui.card>
        </div>
    </div>

    @can('respond', $quote)
        <x-ui.modal name="quote-respond" title="Record the customer's answer">
            <form method="POST" action="{{ route('app.quotations.respond', $quote) }}" class="space-y-4" data-once>
                @csrf
                <fieldset class="flex gap-4 text-sm"><legend class="sr-only">Answer</legend>
                    <label class="flex items-center gap-2"><input type="radio" name="answer" value="accepted" checked class="text-brand-600"> Accepted</label>
                    <label class="flex items-center gap-2"><input type="radio" name="answer" value="declined" class="text-brand-600"> Declined</label>
                </fieldset>
                <x-ui.input label="Customer contact" name="name" :value="$quote->customer->name" required />
                <x-ui.textarea label="Note" name="note" rows="2" hint="e.g. accepted by phone; PO number." />
                <x-ui.button type="submit" class="w-full">Save answer</x-ui.button>
            </form>
        </x-ui.modal>
    @endcan
    @can('cancel', $quote)
        <x-ui.modal name="quote-cancel" title="Cancel this quotation?">
            <form method="POST" action="{{ route('app.quotations.cancel', $quote) }}" class="space-y-4" data-once>
                @csrf
                <x-ui.textarea label="Reason" name="note" rows="3" required />
                <x-ui.button type="submit" variant="danger" class="w-full">Cancel quotation</x-ui.button>
            </form>
        </x-ui.modal>
    @endcan
</x-layouts.app>
