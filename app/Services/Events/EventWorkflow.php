<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\StatusChange;
use App\Models\User;
use App\Notifications\EventStatusChanged;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventWorkflow
{
    public function transition(User $actor, Event $event, EventStatus $to, ?string $note = null): void
    {
        $from = $event->status;

        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "An event that is {$from->label()} can't move to {$to->label()}."]);
        }

        if (in_array($to, [EventStatus::Cancelled, EventStatus::OnHold], true) && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Give a reason when cancelling or holding an event.']);
        }

        DB::transaction(function () use ($actor, $event, $from, $to, $note) {
            $event->update(['status' => $to]);

            StatusChange::create([
                'statusable_type' => $event->getMorphClass(), 'statusable_id' => $event->id,
                'from_status' => $from->value, 'to_status' => $to->value,
                'user_id' => $actor->id, 'user_name' => $actor->name, 'note' => $note, 'created_at' => now(),
            ]);

            Audit::record('status_changed', "Event {$event->reference}: {$from->label()} → {$to->label()}", $event, ['status' => $from->value], ['status' => $to->value]);
        });

        if ($event->projectManager && ! $event->projectManager->is($actor) && $event->projectManager->is_active) {
            $event->projectManager->notify(new EventStatusChanged($event, $from, $to, $actor->name));
        }
    }
}
