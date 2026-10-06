{{-- Internal notes. $notes collection; $action: POST url or null when read-only. --}}
@php use App\Support\Format; @endphp
@if ($action)
    <form method="POST" action="{{ $action }}" class="mb-5 space-y-2" data-once>
        @csrf
        <x-ui.textarea name="body" rows="3" placeholder="Add an internal note (never shown to the customer)…" aria-label="New note" :id="'note-'.md5($action)" />
        <div class="flex justify-end"><x-ui.button type="submit" size="sm" variant="dark" icon="plus">Add note</x-ui.button></div>
    </form>
@endif
@forelse ($notes as $note)
    <article class="border-t border-ink-100 py-3 first:border-0 first:pt-0">
        <p class="text-sm whitespace-pre-line text-ink-800">{{ $note->body }}</p>
        <p class="mt-1 text-xs text-ink-400">{{ $note->user_name ?? 'System' }} · {{ Format::datetime($note->created_at) }}</p>
    </article>
@empty
    <p class="text-sm text-ink-500">No notes yet.</p>
@endforelse
