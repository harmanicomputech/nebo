<?php

namespace App\Services\Booking;

use App\Enums\RequestStatus;
use App\Models\EventRequest;
use App\Models\Note;
use App\Models\StatusChange;
use App\Models\User;
use App\Notifications\RequestAssigned;
use App\Notifications\RequestStatusChanged;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestWorkflow
{
    public function transition(User $actor, EventRequest $request, RequestStatus $to, ?string $note = null): void
    {
        $from = $request->status;

        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "A request that is {$from->label()} can't move to {$to->label()}."]);
        }

        if (in_array($to, [RequestStatus::Cancelled, RequestStatus::Declined], true) && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Give a reason when cancelling or declining.']);
        }

        DB::transaction(function () use ($actor, $request, $from, $to, $note) {
            $request->update(['status' => $to]);

            StatusChange::create([
                'statusable_type' => $request->getMorphClass(), 'statusable_id' => $request->id,
                'from_status' => $from->value, 'to_status' => $to->value,
                'user_id' => $actor->id, 'user_name' => $actor->name, 'note' => $note, 'created_at' => now(),
            ]);

            Audit::record('status_changed', "Request {$request->reference}: {$from->label()} → {$to->label()}", $request, ['status' => $from->value], ['status' => $to->value]);
        });

        if ($request->assigned_to && $request->assigned_to !== $actor->id) {
            $request->assignee?->notify(new RequestStatusChanged($request, $from, $to, $actor->name));
        }
    }

    public function assign(User $actor, EventRequest $request, ?User $assignee): void
    {
        if ($assignee && (! $assignee->is_active || ! $assignee->can('requests.view'))) {
            throw ValidationException::withMessages(['assigned_to' => 'Choose an active user who can see requests.']);
        }

        $request->update(['assigned_to' => $assignee?->id]);

        if ($assignee && ! $assignee->is($actor)) {
            $assignee->notify(new RequestAssigned($request, $actor->name));
        }
    }

    public function addNote(User $actor, EventRequest $request, string $body): Note
    {
        return $request->notes()->create(['body' => $body, 'user_id' => $actor->id, 'user_name' => $actor->name]);
    }
}
