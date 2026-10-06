{{-- Status history. $changes: StatusChange collection; $labels: callable(string): string --}}
@php use App\Support\Format; @endphp
@if ($changes->isEmpty())
    <x-ui.empty-state icon="history" title="No history yet" />
@else
    <ol>
        @foreach ($changes as $change)
            <li class="relative flex gap-4 pb-6 last:pb-0">
                <span class="absolute top-8 bottom-0 left-4 w-px bg-ink-100" aria-hidden="true"></span>
                <span class="relative grid size-8 shrink-0 place-items-center rounded-full {{ $loop->first ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600' }}"><x-ui.icon name="history" class="size-4" /></span>
                <div class="min-w-0 pt-0.5">
                    <p class="text-sm font-semibold text-ink-900">
                        @if ($change->from_status){{ $labels($change->from_status) }} <span class="text-ink-400">→</span> @endif{{ $labels($change->to_status) }}
                    </p>
                    @if ($change->note)<p class="mt-0.5 text-sm text-ink-600">“{{ $change->note }}”</p>@endif
                    <p class="mt-1 text-xs text-ink-400">{{ $change->user_name ?? 'System' }} · <time datetime="{{ $change->created_at->toIso8601String() }}">{{ Format::datetime($change->created_at) }}</time></p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
