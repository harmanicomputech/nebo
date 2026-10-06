{{-- Responsive table shell: scrolls horizontally on small screens. --}}
<div {{ $attributes->merge(['class' => 'relative overflow-x-auto']) }}>
    <table class="table-nebo">
        @isset($head)<thead><tr>{{ $head }}</tr></thead>@endisset
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
