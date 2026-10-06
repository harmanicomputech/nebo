<?php

namespace App\Services\Events;

use App\Enums\AllocationState;
use App\Enums\EventStatus;
use App\Enums\InventoryTransactionType as T;
use App\Models\AssetStatus;
use App\Models\Event;
use App\Models\StatusChange;
use App\Models\User;
use App\Notifications\EventStatusChanged;
use App\Services\Allocation\AllocationService;
use App\Services\Inventory\InventoryLedger;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventWorkflow
{
    public function __construct(private AllocationService $allocations, private InventoryLedger $ledger) {}

    /** Checked-out units become Deployed when the event goes live. */
    private function markDeployed(Event $event): void
    {
        $deployed = AssetStatus::byCode('deployed');

        $event->allocations()->where('state', AllocationState::CheckedOut)->whereNotNull('asset_id')->with(['asset', 'equipment'])->get()
            ->each(function ($allocation) use ($deployed, $event) {
                $from = $allocation->asset->status_id;
                $allocation->asset->forceFill(['status_id' => $deployed->id])->save();
                $this->ledger->record(T::Deployed, $allocation->equipment, $allocation->asset, ['event_id' => $event->id, 'from_status_id' => $from, 'to_status_id' => $deployed->id, 'note' => "{$event->reference} is live"]);
            });
    }

    public function transition(User $actor, Event $event, EventStatus $to, ?string $note = null): void
    {
        $from = $event->status;

        if (! $from->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "An event that is {$from->label()} can't move to {$to->label()}."]);
        }

        if (in_array($to, [EventStatus::Cancelled, EventStatus::OnHold], true) && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Give a reason when cancelling or holding an event.']);
        }

        $out = $event->allocations()->where('state', AllocationState::CheckedOut)->count();
        if ($out && in_array($to, [EventStatus::Cancelled, EventStatus::Completed], true)) {
            throw ValidationException::withMessages(['status' => "{$out} item(s) are still checked out. Check them back in first."]);
        }

        DB::transaction(function () use ($actor, $event, $from, $to, $note) {
            $event->update(['status' => $to]);

            if (in_array($to, [EventStatus::Cancelled, EventStatus::Completed], true)) {
                $this->allocations->releaseReserved($actor, $event, $to === EventStatus::Cancelled ? "Event {$event->reference} cancelled" : "Not used at {$event->reference}");
            }

            if ($to === EventStatus::InProgress) {
                $this->markDeployed($event);
            }

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
