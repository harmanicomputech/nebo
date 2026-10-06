<?php

namespace App\Services\Allocation;

use App\Enums\AllocationState;
use App\Enums\InventoryTransactionType as T;
use App\Enums\LoadStatus;
use App\Models\AssetStatus;
use App\Models\Event;
use App\Models\LoadList;
use App\Models\LoadListItem;
use App\Models\StatusChange;
use App\Models\User;
use App\Services\Inventory\InventoryLedger;
use App\Services\ReferenceGenerator;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Production load-out (brief §26): a load list per event, items picked →
 * loaded → checked, then dispatch, which checks every allocation out.
 */
class LoadListService
{
    public function __construct(private ReferenceGenerator $references, private InventoryLedger $ledger) {}

    /** Creates the event's load list, or adds newly allocated items to it. */
    public function sync(User $actor, Event $event): LoadList
    {
        return DB::transaction(function () use ($actor, $event) {
            $list = $event->loadList ?? $event->loadList()->create([
                'reference' => $this->references->next('load_list'),
                'status' => LoadStatus::Pending,
                'prepared_by' => $actor->id,
            ]);

            if ($list->status === LoadStatus::Dispatched) {
                return $list;
            }

            $reserved = $event->allocations()->where('state', AllocationState::Reserved)->whereDoesntHave('loadListItem')->pluck('id');
            foreach ($reserved as $id) {
                $list->items()->create(['allocation_id' => $id, 'status' => LoadStatus::Pending]);
            }

            $this->refreshStatus($list);

            return $list;
        });
    }

    public function setItemStatus(User $actor, LoadListItem $item, LoadStatus $status, ?string $caseLabel = null): void
    {
        $item->loadMissing('loadList');

        if ($item->loadList->status === LoadStatus::Dispatched) {
            throw ValidationException::withMessages(['status' => 'This load list has been dispatched.']);
        }

        if (! in_array($status, LoadStatus::itemStatuses(), true)) {
            throw ValidationException::withMessages(['status' => 'Invalid status.']);
        }

        $item->update([
            'status' => $status,
            'case_label' => $caseLabel ?? $item->case_label,
            'checked_by' => $status === LoadStatus::Checked ? $actor->id : null,
            'checked_at' => $status === LoadStatus::Checked ? now() : null,
        ]);

        $this->refreshStatus($item->loadList);
    }

    /** Marks every item at least as far as $status (bulk "all picked" etc.). */
    public function advanceAll(User $actor, LoadList $list, LoadStatus $status): void
    {
        foreach ($list->items()->get() as $item) {
            if ($item->status->rank() < $status->rank()) {
                $this->setItemStatus($actor, $item->setRelation('loadList', $list), $status);
            }
        }
    }

    /**
     * Dispatch: every item must be checked. Allocations become checked out,
     * assets Checked Out (usage + 1), and the ledger records the movement.
     */
    public function dispatch(User $actor, LoadList $list): void
    {
        $list->loadMissing(['event', 'items.allocation.asset.status', 'items.allocation.equipment']);
        $event = $list->event;

        if ($list->status === LoadStatus::Dispatched) {
            throw ValidationException::withMessages(['load_list' => 'Already dispatched.']);
        }

        if ($list->items->isEmpty()) {
            throw ValidationException::withMessages(['load_list' => 'Nothing to dispatch. Allocate equipment first.']);
        }

        $unchecked = $list->items->filter(fn ($i) => $i->status !== LoadStatus::Checked)->count();
        if ($unchecked) {
            throw ValidationException::withMessages(['load_list' => "{$unchecked} item(s) haven't been checked yet."]);
        }

        if (! $event->status->holdsResources()) {
            throw ValidationException::withMessages(['load_list' => "{$event->name} is {$event->status->label()}."]);
        }

        DB::transaction(function () use ($actor, $list, $event) {
            $checkedOut = AssetStatus::byCode('checked_out');

            foreach ($list->items as $item) {
                $allocation = $item->allocation;
                if ($allocation->state !== AllocationState::Reserved) {
                    continue;
                }

                $allocation->update(['state' => AllocationState::CheckedOut, 'checked_out_at' => now()]);

                if ($asset = $allocation->asset) {
                    $from = $asset->status_id;
                    $asset->forceFill(['status_id' => $checkedOut->id, 'usage_count' => $asset->usage_count + 1])->save();
                    $this->ledger->record(T::CheckedOut, $allocation->equipment, $asset, [
                        'event_id' => $event->id, 'from_status_id' => $from, 'to_status_id' => $checkedOut->id,
                        'from_location_id' => $asset->location_id, 'note' => "Dispatched to {$event->name} ({$list->reference})",
                    ]);
                } else {
                    $this->ledger->record(T::CheckedOut, $allocation->equipment, null, [
                        'event_id' => $event->id, 'quantity' => $allocation->quantity, 'from_location_id' => $allocation->location_id,
                        'note' => "Dispatched to {$event->name} ({$list->reference})",
                    ]);
                }
            }

            $list->update(['status' => LoadStatus::Dispatched, 'dispatched_at' => now(), 'dispatched_by' => $actor->id]);
            StatusChange::create([
                'statusable_type' => $list->getMorphClass(), 'statusable_id' => $list->id,
                'from_status' => LoadStatus::Checked->value, 'to_status' => LoadStatus::Dispatched->value,
                'user_id' => $actor->id, 'user_name' => $actor->name, 'created_at' => now(),
            ]);
            Audit::record('dispatched', "Load list {$list->reference} dispatched for {$event->reference} ({$list->items->count()} lines)", $event);
        });
    }

    private function refreshStatus(LoadList $list): void
    {
        if ($list->status === LoadStatus::Dispatched) {
            return;
        }

        $ranks = $list->items()->pluck('status')->map(fn ($s) => LoadStatus::from($s instanceof LoadStatus ? $s->value : $s)->rank());
        $status = $ranks->isEmpty() ? LoadStatus::Pending : LoadStatus::cases()[$ranks->min()];

        if ($list->status !== $status) {
            $list->update(['status' => $status]);
        }
    }
}
