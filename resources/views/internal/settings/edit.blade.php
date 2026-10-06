@php $canManage = auth()->user()->can('settings.manage'); @endphp
<x-layouts.app title="Settings">
    <x-ui.page-header title="Settings" description="Company details, reference number formats and notification recipients. Every change is audited."
        :breadcrumbs="['Administration' => null, 'Settings' => null]" />

    <form method="POST" action="{{ route('app.settings.update') }}" class="space-y-6" data-once>
        @csrf @method('PUT')
        <fieldset @disabled(! $canManage) class="space-y-6">
            <x-ui.card title="Company" description="Shown on the public site, emails and (later) quotations.">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input label="Company name" name="company_name" :value="$values['company.name']" required />
                    <x-ui.input label="Coverage" name="company_coverage" :value="$values['company.coverage']" hint="e.g. Nationwide — Nigeria" />
                    <x-ui.input label="Contact email" name="company_email" type="email" :value="$values['company.email']" required />
                    <x-ui.input label="Contact phone" name="company_phone" type="tel" :value="$values['company.phone']" />
                    <x-ui.textarea label="Address" name="company_address" :value="$values['company.address']" rows="2" class="md:col-span-2" />
                </div>
            </x-ui.card>

            <x-ui.card title="Reference numbers" description="Tokens: {YYYY} year, {YY} short year, {MM} month, {SEQ:5} counter padded to 5 digits. Counters restart each year (or month if {MM} is used).">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input label="Booking requests" name="references_request" :value="$values['references.request']" required />
                    <x-ui.input label="Events" name="references_event" :value="$values['references.event']" required />
                    <x-ui.input label="Quotations" name="references_quotation" :value="$values['references.quotation']" required />
                    <x-ui.input label="Load lists" name="references_load_list" :value="$values['references.load_list']" required />
                </div>
            </x-ui.card>

            <x-ui.card title="Notifications" description="In-app notifications go to users with the right permissions. Email copies of new booking requests can also go to these addresses once mail is configured.">
                <x-ui.input label="New request email recipients" name="notifications_request_recipients" :value="$values['notifications.request_recipients']" placeholder="bookings@example.com, ops@example.com" hint="Comma-separated." />
            </x-ui.card>
        </fieldset>

        @if ($canManage)
            <div class="flex justify-end"><x-ui.button type="submit" icon="check">Save settings</x-ui.button></div>
        @else
            <p class="text-sm text-ink-500">You can view settings but not change them.</p>
        @endif
    </form>
</x-layouts.app>
