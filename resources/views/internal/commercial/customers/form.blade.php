@php $editing = $customer->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$customer->displayName() : 'Add customer'">
    <x-ui.page-header :title="$editing ? 'Edit customer' : 'Add customer'" :breadcrumbs="['Commercial' => null, 'Customers' => route('app.customers.index'), ($editing ? $customer->displayName() : 'New') => null]" />
    <form method="POST" action="{{ $editing ? route('app.customers.update', $customer) : route('app.customers.store') }}" class="mx-auto max-w-3xl space-y-6" data-once>
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-ui.card title="Profile">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input label="Contact person" name="name" :value="$customer->name" required autocomplete="off" />
                <x-ui.input label="Company / organisation" name="company" :value="$customer->company" />
                <x-ui.select label="Type" name="type" :options="$types" :value="$customer->type" placeholder="Not set" />
                <x-ui.input label="Email" name="email" type="email" :value="$customer->email" />
                <x-ui.input label="Phone" name="phone" type="tel" :value="$customer->phone" hint="Nigerian numbers are saved as +234…" />
                <x-ui.input label="City" name="city" :value="$customer->city" />
                <x-ui.input label="State" name="state" :value="$customer->state" />
                <x-ui.input label="Address" name="address" :value="$customer->address" class="sm:col-span-2" />
                <x-ui.textarea label="Remarks" name="remarks" :value="$customer->remarks" rows="3" class="sm:col-span-2" hint="Standing information, e.g. billing contact or venue preferences. Use Notes for dated updates." />
            </div>
        </x-ui.card>
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <x-ui.button variant="secondary" :href="$editing ? route('app.customers.show', $customer) : route('app.customers.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">{{ $editing ? 'Save customer' : 'Add customer' }}</x-ui.button>
        </div>
    </form>
</x-layouts.app>
