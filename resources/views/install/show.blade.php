<x-layouts.auth title="Install">
    <h1 class="mt-10 text-2xl font-semibold text-ink-900 lg:mt-0">Install Nebo Stage</h1>
    <p class="mt-2 text-sm text-ink-500">This sets up the database and your administrator account. It takes a minute, or a few minutes with sample data.</p>

    <section class="mt-8" aria-labelledby="req-title">
        <h2 id="req-title" class="text-sm font-semibold text-ink-900">Server check</h2>
        @if ($ready)
            <details class="mt-3 text-sm">
                <summary class="flex cursor-pointer items-center gap-2 text-ink-700"><x-ui.icon name="circle-check" class="size-4 shrink-0 text-emerald-600" />All {{ count($requirements) }} checks passed <span class="text-xs text-ink-500">(show)</span></summary>
                <ul class="mt-2 space-y-1.5 pl-6 text-ink-600">
                    @foreach ($requirements as [$label])<li>{{ $label }}</li>@endforeach
                </ul>
            </details>
        @else
            <ul class="mt-3 space-y-1.5 text-sm">
                @foreach ($requirements as [$label, $ok, $hint])
                    @if ($ok)
                        <li class="flex items-center gap-2 text-ink-600"><x-ui.icon name="circle-check" class="size-4 shrink-0 text-emerald-600" />{{ $label }}</li>
                    @else
                        <li class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-brand-800">
                            <span class="flex items-center gap-2 font-semibold"><x-ui.icon name="circle-x" class="size-4 shrink-0" />{{ $label }}</span>
                            <span class="mt-1 block text-xs">{{ $hint }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </section>

    @if (! $ready)
        <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">Fix the items above, then reload this page.</p>
        <x-ui.button :href="route('install.show')" class="mt-4 w-full" icon="refresh-cw">Check again</x-ui.button>
    @else
        <form method="POST" action="{{ route('install.store') }}" class="mt-8 space-y-8" data-once x-data="{ mailer: @js(old('mail_mailer', 'smtp')) }">
            @csrf
            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-ink-900">Website</legend>
                <x-ui.input label="Website address" name="app_url" type="url" :value="$appUrl" required hint="Use https:// if the domain has an SSL certificate (needed to install the app on phones)." />
                <x-ui.input label="Company name" name="company" value="Nebo Stage" />
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-ink-900">Database</legend>
                <p class="text-xs text-ink-500">Create a MySQL database and user in DirectAdmin (Account Manager → MySQL Management), then enter them here.</p>
                <div class="grid grid-cols-[1fr_6rem] gap-3">
                    <x-ui.input label="Host" name="db_host" value="localhost" required />
                    <x-ui.input label="Port" name="db_port" type="number" value="3306" required />
                </div>
                <x-ui.input label="Database name" name="db_database" required autocomplete="off" />
                <x-ui.input label="Database user" name="db_username" required autocomplete="off" />
                <x-ui.input label="Database password" name="db_password" type="password" autocomplete="new-password" />
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-ink-900">Administrator account</legend>
                <x-ui.input label="Full name" name="admin_name" required autocomplete="name" />
                <x-ui.input label="Email address" name="admin_email" type="email" required autocomplete="username" />
                <x-ui.input label="Password" name="admin_password" type="password" required autocomplete="new-password" hint="At least 10 characters with upper and lower case letters and a number." />
                <x-ui.input label="Confirm password" name="admin_password_confirmation" id="f-admin_password_confirmation" type="password" required autocomplete="new-password" />
            </fieldset>

            <fieldset class="space-y-4">
                <legend class="text-sm font-semibold text-ink-900">Email sending</legend>
                <x-ui.select label="How should the system send email?" name="mail_mailer" x-model="mailer"
                    :options="['smtp' => 'SMTP email account (recommended)', 'sendmail' => 'Server mail program (sendmail)', 'log' => 'Don’t send email yet']" />
                <div x-show="mailer === 'smtp'" class="space-y-4">
                    <div class="grid grid-cols-[1fr_6rem] gap-3">
                        <x-ui.input label="SMTP server" name="mail_host" placeholder="mail.yourdomain.com" />
                        <x-ui.input label="Port" name="mail_port" type="number" value="587" />
                    </div>
                    <x-ui.input label="SMTP username" name="mail_username" autocomplete="off" hint="Usually the full email address of an account made in DirectAdmin → E-mail Manager." />
                    <x-ui.input label="SMTP password" name="mail_password" type="password" autocomplete="new-password" />
                </div>
                <x-ui.input label="Send emails from" name="mail_from" type="email" placeholder="bookings@yourdomain.com" hint="Leave blank to use the administrator email." x-show="mailer !== 'log'" />
            </fieldset>

            <label class="flex items-start gap-3 rounded-lg border border-ink-200 p-4 text-sm text-ink-700">
                <input type="hidden" name="demo" value="0">
                <input type="checkbox" name="demo" value="1" @checked(old('demo')) class="mt-0.5 size-4 rounded border-ink-300 text-brand-600 focus:ring-brand-600">
                <span><span class="block font-semibold text-ink-900">Load sample data to try the system</span>
                    A year of realistic productions, equipment, customers, quotes, trips and repairs, plus a sign-in account for every role. Clear it with one button in Settings → System when you are ready to go live.</span>
            </label>

            <x-ui.button type="submit" size="lg" class="w-full" icon-right="arrow-right">Install</x-ui.button>
        </form>
    @endif
</x-layouts.auth>
