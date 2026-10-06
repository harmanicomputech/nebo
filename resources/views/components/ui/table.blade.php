@props(['stack' => true])
{{-- Responsive table shell: scrolls horizontally on small screens; with stack (default) its rows become labelled cards on phones (D70). --}}
<div {{ $attributes->merge(['class' => 'relative overflow-x-auto']) }} @if ($stack) data-stack @endif>
    <table class="table-nebo">
        @isset($head)<thead><tr>{{ $head }}</tr></thead>@endisset
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
