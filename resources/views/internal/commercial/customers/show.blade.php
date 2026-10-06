@php use App\Support\Format; $u = auth()->user(); @endphp
<x-layouts.app :title="$customer->displayName()">
    <x-ui.page-header :title="$customer->company ?: $customer->name" :description="collect([$customer->company ? $customer->name : null, $customer->typeLabel(), $customer->city])->filter()->implode(' · ')"
        :breadcrumbs="['Commercial' => null, 'Customers' => route('app.customers.index'), $customer->displayName() => null]">
        <x-slot:actions>
            @can('update', $customer)<x-ui.button variant="secondary" icon="pencil" :href="route('app.customers.edit', $customer)">Edit</x-ui.button>@endcan
            @can('create', App\Models\Quotation::class)<x-ui.button icon="receipt" :href="route('app.quotations.create', ['customer' => $customer->id])">New quotation</x-ui.button>@endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($customer->needs_review)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
            <p class="font-semibold">This profile matched an existing customer with different details.</p>
            @if ($similar->isNotEmpty())
                <p class="mt-1">Possible duplicates: @foreach ($similar as $s)<a href="{{ route('app.customers.show', $s) }}" class="font-semibold underline">{{ $s->displayName() }}</a>@if (! $loop->last), @endif @endforeach</p>
            @endif
            @can('update', $customer)
                <form method="POST" action="{{ route('app.customers.reviewed', $customer) }}" class="mt-3">@csrf<x-ui.button type="submit" size="sm" variant="secondary" icon="check">It's a different customer — mark checked</x-ui.button></form>
            @endcan
        </div>
    @endif

    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-ui.stat label="Requests" :value="$stats['requests']" icon="inbox" />
        <x-ui.stat label="Events" :value="$stats['events']" icon="calendar-range" />
        @can('financial.view')
            <x-ui.stat label="Accepted" :value="Format::naira($stats['won'])" icon="circle-check" tone="success" />
            <x-ui.stat label="Awaiting answer" :value="Format::naira($stats['open'])" icon="receipt" tone="warning" />
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card title="Contact" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    @foreach (array_filter(['Contact' => $customer->name, 'Company' => $customer->company, 'Type' => $customer->typeLabel(), 'Address' => $customer->address, 'City / state' => collect([$customer->city, $customer->state])->filter()->implode(', ') ?: null]) as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right font-medium">{{ $value }}</dd></div>
                    @endforeach
                    @if ($customer->email)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Email</dt><dd class="min-w-0 truncate text-right"><a href="mailto:{{ $customer->email }}" class="text-brand-700 hover:underline">{{ $customer->email }}</a></dd></div>@endif
                    @if ($customer->phone)<div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Phone</dt><dd class="text-right"><a href="tel:{{ $customer->phone }}" class="text-brand-700 hover:underline">{{ $customer->phone }}</a></dd></div>@endif
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Customer since</dt><dd class="text-right">{{ Format::date($customer->created_at) }}</dd></div>
                </dl>
                @if ($customer->remarks)<p class="border-t border-ink-100 px-5 py-4 text-sm whitespace-pre-line text-ink-600">{{ $customer->remarks }}</p>@endif
            </x-ui.card>

            @if ($mergeOptions)
                <x-ui.card title="Merge a duplicate" description="Moves the other profile's requests, events, quotations, notes and documents here, fills any blanks, and archives it.">
                    <x-ui.button variant="danger" class="w-full" icon="git-merge" x-data x-on:click="$dispatch('open-modal', 'customer-merge')">Merge a duplicate into this profile</x-ui.button>
                </x-ui.card>
                <x-ui.modal name="customer-merge" title="Merge a duplicate profile">
                    <form method="POST" action="{{ route('app.customers.merge', $customer) }}" class="space-y-4" data-once>
                        @csrf
                        <x-ui.select label="Duplicate profile" name="duplicate_id" :options="$mergeOptions" placeholder="Choose the duplicate" required />
                        <p class="flex gap-2 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-900"><x-ui.icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />Everything on the chosen profile moves to {{ $customer->displayName() }}, and the chosen profile is archived. This can't be undone from the app.</p>
                        <x-ui.button type="submit" variant="primary" class="w-full" icon="git-merge">Merge</x-ui.button>
                    </form>
                </x-ui.modal>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            @can('quotations.view')
                @include('internal.commercial.quotations._list', ['quotes' => $quotations, 'newUrl' => null])
            @endcan
            @if ($requests->isNotEmpty())
                <x-ui.card title="Requests" :padding="false">
                    <ul class="divide-y divide-ink-100">
                        @foreach ($requests as $r)
                            <li><a href="{{ route('app.requests.show', $r) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $r->event_name }}</span><span class="block text-xs text-ink-500"><span class="font-mono">{{ $r->reference }}</span> · {{ Format::date($r->event_date) }}</span></span>
                                <x-ui.badge :tone="$r->status->tone()">{{ $r->status->label() }}</x-ui.badge>
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
            @if ($events->isNotEmpty())
                <x-ui.card title="Events" :padding="false">
                    <ul class="divide-y divide-ink-100">
                        @foreach ($events as $e)
                            <li><a href="{{ route('app.events.show', $e) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 hover:bg-ink-50 sm:px-6">
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $e->name }}</span><span class="block text-xs text-ink-500"><span class="font-mono">{{ $e->reference }}</span> · {{ Format::date($e->starts_at) }} · {{ $e->venue }}</span></span>
                                <x-ui.badge :tone="$e->status->tone()">{{ $e->status->label() }}</x-ui.badge>
                            </a></li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
            <x-ui.card title="Notes">
                @include('internal.partials.notes', ['notes' => $customer->notes, 'action' => $u->can('update', $customer) ? route('app.customers.notes', $customer) : null])
            </x-ui.card>
            <x-ui.card title="Documents" description="Contracts, purchase orders and company papers.">
                @include('internal.partials.documents', ['documents' => $customer->documents, 'uploadUrl' => $u->can('documents.manage') && $u->can('update', $customer) ? route('app.documents.store', ['customer', $customer->id]) : null])
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
