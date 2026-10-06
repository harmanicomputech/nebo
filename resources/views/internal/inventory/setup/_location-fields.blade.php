@php $p = $location->exists ? 'loc'.$location->id.'-' : 'loc-new-'; @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <x-ui.input label="Name" name="name" :value="$location->name" required :id="$p.'name'" />
    <x-ui.input label="Code" name="code" :value="$location->code" required :id="$p.'code'" hint="Short, unique: MAIN, ABJ-WH" />
</div>
<x-ui.select label="Type" name="type" :options="app(App\Support\Lookups::class)->options('location_type', $location->type)" :value="$location->type" required :id="$p.'type'" />
<x-ui.input label="Address" name="address" :value="$location->address" :id="$p.'address'" />
<x-ui.textarea label="Notes" name="notes" :value="$location->notes" rows="2" :id="$p.'notes'" />
