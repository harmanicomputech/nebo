@php
    use App\Support\Format;
    use App\Support\QrCode;
    $canCosts = auth()->user()->can('inventory.costs');
    $canChange = auth()->user()->can('changeState', $asset);
    $managed = ! $asset->status->is_manual;
@endphp
<x-layouts.app :title="$asset->asset_tag">
    <x-ui.page-header :title="$asset->asset_tag" :description="$asset->equipment->name"
        :breadcrumbs="['Inventory' => null, 'Assets' => route('app.inventory.assets.index'), $asset->asset_tag => null]">
        <x-slot:actions>
            <x-ui.badge :tone="$asset->status->tone" class="!text-sm">{{ $asset->status->label }}</x-ui.badge>
            @if ($asset->trashed())
                <x-ui.badge tone="neutral">Archived</x-ui.badge>
                @can('restore', $asset)<form method="POST" action="{{ route('app.inventory.assets.restore', $asset->id) }}">@csrf<x-ui.button type="submit" variant="secondary" icon="history">Restore</x-ui.button></form>@endcan
            @else
                @can('update', $asset)<x-ui.button variant="secondary" icon="pencil" :href="route('app.inventory.assets.edit', $asset)">Edit details</x-ui.button>@endcan
                <x-ui.button variant="secondary" icon="qr-code" :href="route('app.inventory.labels', ['assets' => [$asset->id]])" target="_blank">Print label</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($managed && ! $asset->trashed())
        <div class="mb-6 flex gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="note">
            <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
            <p>This asset is <strong>{{ $asset->status->label }}</strong>. Its status and location are managed by event allocation and returns, so they can't be changed here.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-ui.card>
                <div class="flex items-start gap-4">
                    <div class="shrink-0 rounded-lg bg-white p-1 ring-1 ring-ink-100 [&_svg]:size-24">{!! QrCode::svg(route('app.scan', $asset->qr_token), 96) !!}</div>
                    <div class="min-w-0 text-sm">
                        <p class="font-mono text-lg font-semibold">{{ $asset->asset_tag }}</p>
                        <a href="{{ route('app.inventory.equipment.show', $asset->equipment) }}" class="font-medium text-brand-700 hover:underline">{{ $asset->equipment->name }}</a>
                        <p class="text-xs text-ink-500">{{ $asset->equipment->category?->fullName() }}</p>
                        <p class="mt-2 text-xs text-ink-500">Scan the code to open this page.</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Current state" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Status</dt><dd><x-ui.badge :tone="$asset->status->tone">{{ $asset->status->label }}</x-ui.badge></dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Condition</dt><dd class="font-medium">{{ $asset->conditionLabel() }}@if ($asset->conditionBlocksAllocation()) <x-ui.badge tone="danger" class="ml-1">Blocks allocation</x-ui.badge>@endif</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Location</dt><dd class="text-right font-medium">{{ $asset->location?->name ?? '—' }}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Can be allocated</dt><dd>@if ($asset->isAllocatable())<x-ui.badge tone="success">Yes</x-ui.badge>@else<x-ui.badge tone="neutral">No</x-ui.badge>@endif</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Last inspected</dt><dd>{{ Format::datetime($asset->last_inspected_at) }}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Next maintenance</dt><dd @class(['font-semibold text-brand-700' => $asset->next_maintenance_due_on?->isPast()])>{{ Format::date($asset->next_maintenance_due_on) }}</dd></div>
                    <div class="flex items-center justify-between gap-4 px-5 py-3"><dt class="text-ink-500">Times deployed</dt><dd class="tabular-nums">{{ $asset->usage_count }}</dd></div>
                </dl>
                @if ($canChange && ! $asset->trashed())
                    <div class="grid grid-cols-3 gap-2 border-t border-ink-100 p-4">
                        <x-ui.button size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'asset-condition')">Condition</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'asset-status')" :disabled="$managed">Status</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'asset-move')" :disabled="$managed">Move</x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Identity & purchase" :padding="false">
                <dl class="divide-y divide-ink-100 text-sm">
                    @foreach (array_filter([
                        'Serial number' => $asset->serial_number,
                        'Barcode' => $asset->barcode,
                        'Manufacturer' => $asset->equipment->manufacturer,
                        'Model' => $asset->equipment->model,
                        'Purchased' => $asset->purchase_date ? Format::date($asset->purchase_date) : null,
                        'Supplier' => $asset->supplier,
                        'Purchase cost' => $canCosts && $asset->purchase_cost_kobo !== null ? Format::naira($asset->purchase_cost_kobo) : null,
                        'Current value' => $canCosts && $asset->current_value_kobo !== null ? Format::naira($asset->current_value_kobo) : null,
                        'Warranty until' => $asset->warranty_expires_on ? Format::date($asset->warranty_expires_on).($asset->warranty_expires_on->isPast() ? ' (expired)' : '') : null,
                    ]) as $label => $value)
                        <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-ink-500">{{ $label }}</dt><dd class="text-right font-medium {{ in_array($label, ['Serial number', 'Barcode']) ? 'font-mono text-xs' : '' }}">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($asset->notes)<p class="border-t border-ink-100 px-5 py-4 text-sm whitespace-pre-line text-ink-600">{{ $asset->notes }}</p>@endif
            </x-ui.card>

            @can('delete', $asset)
                @unless ($managed)
                    <x-ui.card title="Archive asset" description="Removes it from lists. To take it out of service, set the status to Retired instead. History is kept.">
                        <x-ui.confirm :action="route('app.inventory.assets.destroy', $asset)" method="DELETE" icon="archive" title="Archive {{ $asset->asset_tag }}?" message="It will disappear from inventory lists. You can restore it later." confirm="Archive">Archive</x-ui.confirm>
                    </x-ui.card>
                @endunless
            @endcan
        </div>

        <div class="space-y-6 lg:col-span-2">
        @if ($bookings->isNotEmpty())
            <x-ui.card title="Booked on" description="Events holding this unit." :padding="false">
                <ul class="divide-y divide-ink-100">
                    @foreach ($bookings as $b)
                        <li><a href="{{ route('app.events.show', [$b->event, 'tab' => 'allocation']) }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-ink-50">
                            <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $b->event->name }}</span><span class="block text-xs text-ink-500">{{ Format::datetime($b->hold_starts_at, 'D j M, g:ia') }} → {{ Format::datetime($b->hold_ends_at, 'D j M, g:ia') }}</span></span>
                            <x-ui.badge :tone="$b->state->tone()">{{ $b->state->label() }}</x-ui.badge>
                        </a></li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
        <x-ui.card title="History" description="Every status, location and condition change.">
            @if ($history->isEmpty())
                <x-ui.empty-state icon="history" title="No history yet" />
            @else
                <ol>@foreach ($history as $t)@include('internal.inventory._ledger-entry', ['t' => $t])@endforeach</ol>
                @if ($history->hasPages())<div class="mt-6 border-t border-ink-100 pt-4">{{ $history->links() }}</div>@endif
            @endif
        </x-ui.card>
        </div>
    </div>

    @if ($canChange && ! $asset->trashed())
        <x-ui.modal name="asset-condition" title="Record condition">
            <form method="POST" action="{{ route('app.inventory.assets.condition', $asset) }}" class="space-y-4" data-once>
                @csrf
                <x-ui.select label="Condition" name="condition" :options="$conditions" :value="$asset->condition" required hint="Damaged, critical or needs-inspection conditions take the unit out of service automatically." />
                <x-ui.textarea label="Inspection notes" name="note" rows="3" />
                <x-ui.button type="submit" class="w-full">Record condition</x-ui.button>
            </form>
        </x-ui.modal>
        @unless ($managed)
            <x-ui.modal name="asset-status" title="Change status">
                <form method="POST" action="{{ route('app.inventory.assets.status', $asset) }}" class="space-y-4" data-once>
                    @csrf
                    <x-ui.select label="New status" name="status_id" :options="$manualStatuses" :value="$asset->status_id" required hint="Lost and Retired need archive permission." />
                    <x-ui.textarea label="Reason" name="note" rows="3" />
                    <x-ui.button type="submit" class="w-full">Change status</x-ui.button>
                </form>
            </x-ui.modal>
            <x-ui.modal name="asset-move" title="Move to another location">
                <form method="POST" action="{{ route('app.inventory.assets.move', $asset) }}" class="space-y-4" data-once>
                    @csrf
                    <x-ui.select label="Location" name="location_id" :options="$locations" :value="$asset->location_id" required />
                    <x-ui.textarea label="Note" name="note" rows="2" />
                    <x-ui.button type="submit" class="w-full">Move</x-ui.button>
                </form>
            </x-ui.modal>
        @endunless
    @endif
</x-layouts.app>
