{{--
    Asks once per device to turn on pop-up notifications (D75). Hidden when
    they are on, blocked, not supported, or the person said "Not now".
--}}
<div x-data="pushToggle" x-cloak x-show="prompt" x-transition class="px-4 pt-4 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 rounded-2xl border border-ink-100 bg-white p-3 shadow-card sm:p-4">
        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-600 text-white"><x-ui.icon name="bell-ring" class="size-5" /></span>
        <p class="min-w-0 flex-1 text-sm text-ink-700">
            <template x-if="state === 'ios-install'"><span><b class="font-semibold text-ink-900">Get notifications on this iPhone:</b> tap the Share button, choose <b>Add to Home Screen</b>, then open Nebo Stage from your home screen.</span></template>
            <template x-if="state !== 'ios-install'"><span><b class="font-semibold text-ink-900">Turn on notifications</b> to get a pop-up on this device for every new notification, even when Nebo Stage is closed.</span></template>
        </p>
        <div class="flex w-full gap-2 sm:w-auto">
            <x-ui.button variant="ghost" size="sm" x-on:click="dismiss()" class="max-sm:flex-1">Not now</x-ui.button>
            <x-ui.button size="sm" icon="bell" x-show="state === 'off'" x-on:click="enable()" ::disabled="busy" class="max-sm:flex-1">Turn on</x-ui.button>
        </div>
    </div>
</div>
