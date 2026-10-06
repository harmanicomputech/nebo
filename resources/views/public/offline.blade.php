<x-layouts.error title="You're offline">
    <x-slot:code><x-ui.icon name="wifi-off" class="size-14 text-brand-500" /></x-slot:code>
    <x-slot:heading>You're offline</x-slot:heading>
    Nebo Stage needs a connection to show live inventory and event data. Check your signal and try again.
    <x-slot:actions><x-ui.button onclick="location.reload()" icon="history">Try again</x-ui.button></x-slot:actions>
</x-layouts.error>
