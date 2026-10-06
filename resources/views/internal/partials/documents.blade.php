{{-- Documents on a record. $documents; $uploadUrl (null = no upload). --}}
@php use App\Support\Format; @endphp
<ul class="divide-y divide-ink-100">
    @forelse ($documents as $doc)
        <li class="flex items-center gap-3 py-3 first:pt-0">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-ink-50 text-ink-500 ring-1 ring-ink-100"><x-ui.icon :name="$doc->icon()" class="size-4" /></span>
            <div class="min-w-0 flex-1">
                @can('view', $doc)
                    <a href="{{ route('app.documents.download', $doc) }}" data-no-busy class="block truncate text-sm font-medium text-ink-900 hover:text-brand-700">{{ $doc->original_name }}</a>
                @else
                    <span class="block truncate text-sm font-medium text-ink-900">{{ $doc->original_name }}</span>
                @endcan
                <span class="block text-xs text-ink-500">{{ $doc->categoryLabel() }} · {{ $doc->humanSize() }} · {{ $doc->source === 'public_form' ? 'from the customer' : ($doc->uploaded_by_name ?? 'System') }} · {{ Format::date($doc->created_at) }}</span>
            </div>
            @can('delete', $doc)
                <x-ui.confirm :action="route('app.documents.destroy', $doc)" method="DELETE" size="sm" variant="ghost" icon="trash-2" title="Remove {{ $doc->original_name }}?" message="It will no longer be listed here. The removal is audited." confirm="Remove" aria-label="Remove {{ $doc->original_name }}" />
            @endcan
        </li>
    @empty
        <li class="py-2 text-sm text-ink-500">No documents.</li>
    @endforelse
</ul>
@if ($uploadUrl)
    <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-ink-100 pt-4" data-once>
        @csrf
        <input type="file" name="files[]" multiple accept="{{ App\Services\Documents\UploadRules::accept() }}" required class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-ink-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white" aria-label="Files to upload">
        <div class="flex gap-2">
            <x-ui.select name="category" :options="app(App\Support\Lookups::class)->options('document_category')" placeholder="Category (optional)" class="flex-1" aria-label="Category" :id="'doc-cat-'.md5($uploadUrl)" />
            <x-ui.button type="submit" size="sm" variant="dark" icon="download">Upload</x-ui.button>
        </div>
        @foreach ($errors->get('files*') as $messages)<p class="text-xs font-medium text-brand-700">{{ $messages[0] }}</p>@endforeach
        <p class="text-xs text-ink-500">{{ App\Services\Documents\UploadRules::describe() }}.</p>
    </form>
@endif
