<x-layouts.error title="Session expired">
    <x-slot:code>419</x-slot:code>
    <x-slot:heading>Session expired</x-slot:heading>
    For your security, this page expired. Go back, refresh, and try again.
    <x-slot:actions><x-ui.button :href="url()->previous()" icon="arrow-left">Go back and refresh</x-ui.button></x-slot:actions>
</x-layouts.error>
