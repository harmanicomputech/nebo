<?php

namespace App\Services\Maintenance;

use App\Enums\InventoryTransactionType as T;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\AssetStatus;
use App\Models\ConditionReport;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentAsset;
use App\Models\MaintenanceRecord;
use App\Models\StatusChange;
use App\Models\User;
use App\Notifications\MaintenanceAssigned;
use App\Services\Inventory\AssetService;
use App\Services\ReferenceGenerator;
use App\Support\Audit\Audit;
use App\Support\Format;
use App\Support\Lookups;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintenance jobs (D50–D52). Every asset status or condition change goes
 * through AssetService, so the ledger records it.
 *
 *   report    → Reported (or Scheduled when a window is given); a fault can
 *               take an in-service unit out of service straight away
 *   schedule  → Scheduled; the window blocks availability (MaintenanceBlocker)
 *   start     → In Progress; the unit becomes Under Maintenance
 *   complete  → Completed; condition recorded, unit back in service (or
 *               not, if the condition still blocks allocation), schedules
 *               advanced
 *   cancel    → Cancelled; the unit returns to service if nothing else
 *               keeps it out
 */
class MaintenanceService
{
    public function __construct(
        private AssetService $assets,
        private ReferenceGenerator $references,
        private Lookups $lookups,
        private MaintenanceScheduler $scheduler,
    ) {}

    /**
     * @param  array{type: string, priority?: string, issue: string, description?: ?string, technician_id?: ?int, scheduled_starts_at?: ?CarbonInterface, scheduled_ends_at?: ?CarbonInterface, out_of_service?: bool, source?: string, event_id?: ?int, schedule_id?: ?int}  $data
     */
    public function report(User $actor, EquipmentAsset $asset, array $data): MaintenanceRecord
    {
        $asset->loadMissing(['status', 'equipment']);

        if ($asset->trashed() || $asset->status->group->isTerminal()) {
            throw ValidationException::withMessages(['asset' => "{$asset->asset_tag} is archived, lost or retired."]);
        }

        if (! in_array($data['type'], $this->lookups->activeKeys('maintenance_type'), true)) {
            throw ValidationException::withMessages(['type' => 'Choose a maintenance type.']);
        }

        $starts = $data['scheduled_starts_at'] ?? null;
        $ends = $data['scheduled_ends_at'] ?? null;
        if ($starts || $ends) {
            $this->assertWindow($asset, $starts, $ends);
        }

        $record = DB::transaction(function () use ($actor, $asset, $data, $starts, $ends) {
            $record = MaintenanceRecord::create([
                'reference' => $this->references->next('maintenance'),
                'asset_id' => $asset->id,
                'equipment_id' => $asset->equipment_id,
                'schedule_id' => $data['schedule_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'type' => $data['type'],
                'priority' => $data['priority'] ?? MaintenancePriority::Normal->value,
                'status' => $starts ? MaintenanceStatus::Scheduled : MaintenanceStatus::Reported,
                'source' => $data['source'] ?? 'manual',
                'issue' => $data['issue'],
                'description' => $data['description'] ?? null,
                'reported_by' => $actor->id,
                'technician_id' => $data['technician_id'] ?? null,
                'scheduled_starts_at' => $starts,
                'scheduled_ends_at' => $ends,
            ]);

            $this->history($actor, $record, null, $record->status);

            if (($data['out_of_service'] ?? false) && $asset->status->is_allocatable) {
                $this->assets->changeStatus($actor, $asset, AssetStatus::byCode('maintenance_required'), "Fault reported ({$record->reference}): {$record->issue}");
            }

            return $record;
        });

        $this->notifyTechnician($record, $actor);

        return $record;
    }

    public function schedule(User $actor, MaintenanceRecord $record, CarbonInterface $starts, CarbonInterface $ends, ?int $technicianId = null): void
    {
        $this->assertCan($record, MaintenanceStatus::Scheduled);
        $this->assertWindow($record->asset, $starts, $ends, $record);

        $before = $record->technician_id;
        DB::transaction(function () use ($actor, $record, $starts, $ends, $technicianId) {
            $from = $record->status;
            $record->update(['status' => MaintenanceStatus::Scheduled, 'scheduled_starts_at' => $starts, 'scheduled_ends_at' => $ends, 'technician_id' => $technicianId ?? $record->technician_id]);
            $this->history($actor, $record, $from, MaintenanceStatus::Scheduled, Format::datetime($starts).' → '.Format::datetime($ends));
        });

        if ($record->technician_id !== $before) {
            $this->notifyTechnician($record, $actor);
        }
    }

    public function start(User $actor, MaintenanceRecord $record, ?string $note = null): void
    {
        $this->assertCan($record, MaintenanceStatus::InProgress);
        $asset = $record->asset()->with(['status', 'equipment'])->first();
        $this->assertWorkable($asset);

        DB::transaction(function () use ($actor, $record, $asset, $note) {
            $from = $record->status;
            $record->update(['status' => MaintenanceStatus::InProgress, 'started_at' => now()]);
            $this->assets->changeStatus($actor, $asset, AssetStatus::byCode('under_maintenance'), "Maintenance started ({$record->reference})");
            $this->history($actor, $record, $from, MaintenanceStatus::InProgress, $note);
        });
    }

    /**
     * @param  array{work_done: string, outcome_condition: string, cost_kobo?: ?int, parts_used?: ?string, next_due_on?: ?string}  $data
     */
    public function complete(User $actor, MaintenanceRecord $record, array $data): void
    {
        $this->assertCan($record, MaintenanceStatus::Completed);
        $asset = $record->asset()->with(['status', 'equipment'])->first();
        $this->assertWorkable($asset);

        if (! in_array($data['outcome_condition'], $this->lookups->activeKeys('condition'), true)) {
            throw ValidationException::withMessages(['outcome_condition' => 'Choose the condition after the work.']);
        }

        DB::transaction(function () use ($actor, $record, $asset, $data) {
            $from = $record->status;
            $fromCondition = $asset->condition;

            $record->update([
                'status' => MaintenanceStatus::Completed,
                'completed_at' => now(),
                'started_at' => $record->started_at ?? now(),
                'work_done' => $data['work_done'],
                'outcome_condition' => $data['outcome_condition'],
                'cost_kobo' => $data['cost_kobo'] ?? null,
                'parts_used' => $data['parts_used'] ?? null,
            ]);

            $this->assets->recordCondition($asset, $data['outcome_condition'], "After {$record->reference}");
            $asset->refresh()->load(['status', 'equipment']);

            ConditionReport::create([
                'asset_id' => $asset->id, 'from_condition' => $fromCondition, 'to_condition' => $data['outcome_condition'],
                'source' => 'maintenance', 'note' => $data['work_done'], 'maintenance_record_id' => $record->id,
                'user_id' => $actor->id, 'user_name' => $actor->name, 'created_at' => now(),
            ]);

            // Back in service unless the condition still blocks allocation or
            // another open job keeps the unit out.
            $blocks = (bool) $this->lookups->meta('condition', $data['outcome_condition'], 'blocks_allocation', false);
            $otherOpen = $asset->maintenanceRecords()->open()->whereKeyNot($record->id)->whereNotIn('status', [MaintenanceStatus::Scheduled->value])->exists();
            $target = match (true) {
                $blocks => AssetStatus::byCode($this->lookups->meta('condition', $data['outcome_condition'], 'sets_status') ?: 'maintenance_required'),
                $otherOpen => AssetStatus::byCode('maintenance_required'),
                default => AssetStatus::byCode('available'),
            };
            if ($asset->status->is_manual) {
                $this->assets->changeStatus($actor, $asset, $target, "{$record->reference} completed", $target->code === 'available' ? T::Repaired : null);
            }

            $this->scheduler->recordDone($record, $data['next_due_on'] ?? null);
            $this->history($actor, $record, $from, MaintenanceStatus::Completed);
        });
    }

    public function cancel(User $actor, MaintenanceRecord $record, string $reason): void
    {
        $this->assertCan($record, MaintenanceStatus::Cancelled);

        if (blank($reason)) {
            throw ValidationException::withMessages(['note' => 'Give a reason for cancelling.']);
        }

        DB::transaction(function () use ($actor, $record, $reason) {
            $from = $record->status;
            $record->update(['status' => MaintenanceStatus::Cancelled]);
            $this->history($actor, $record, $from, MaintenanceStatus::Cancelled, $reason);

            $asset = $record->asset()->with(['status', 'equipment'])->first();
            $outForMaintenance = in_array($asset->status->code, ['maintenance_required', 'under_maintenance'], true);
            $otherOpen = $asset->maintenanceRecords()->open()->whereNot('status', MaintenanceStatus::Scheduled->value)->exists();

            if ($outForMaintenance && ! $otherOpen && ! $asset->conditionBlocksAllocation()) {
                $this->assets->changeStatus($actor, $asset, AssetStatus::byCode('available'), "{$record->reference} cancelled: {$reason}");
            }
        });
    }

    /** Units at an event (or booked) can't be worked on until they are back or released. */
    private function assertWorkable(EquipmentAsset $asset): void
    {
        if (! $asset->status->is_manual) {
            $booking = $asset->allocations()->active()->with('event')->orderBy('hold_starts_at')->first();

            throw ValidationException::withMessages(['status' => "{$asset->asset_tag} is {$asset->status->label}".($booking ? " for {$booking->event->name}" : '').'. Check it in, or release or swap it on the event, first.']);
        }
    }

    /** A scheduled window must be valid and must not overlap the unit's bookings. */
    private function assertWindow(EquipmentAsset $asset, ?CarbonInterface $starts, ?CarbonInterface $ends, ?MaintenanceRecord $ignore = null): void
    {
        if (! $starts || ! $ends || $ends->lte($starts)) {
            throw ValidationException::withMessages(['scheduled_ends_at' => 'Give a start and an end, with the end after the start.']);
        }

        $clash = EquipmentAllocation::query()->active()->where('asset_id', $asset->id)
            ->where('hold_starts_at', '<', $ends)->where('hold_ends_at', '>', $starts)
            ->with('event')->orderBy('hold_starts_at')->first();

        if ($clash) {
            throw ValidationException::withMessages(['scheduled_starts_at' => "{$asset->asset_tag} is booked on {$clash->event->name} (".Format::date($clash->hold_starts_at).' – '.Format::date($clash->hold_ends_at).'). Pick other dates, or swap the unit on the event first.']);
        }
    }

    private function assertCan(MaintenanceRecord $record, MaintenanceStatus $to): void
    {
        if (! $record->status->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => "A job that is {$record->status->label()} can't move to {$to->label()}."]);
        }
    }

    private function history(User $actor, MaintenanceRecord $record, ?MaintenanceStatus $from, MaintenanceStatus $to, ?string $note = null): void
    {
        StatusChange::create([
            'statusable_type' => $record->getMorphClass(), 'statusable_id' => $record->id,
            'from_status' => $from?->value, 'to_status' => $to->value,
            'user_id' => $actor->id, 'user_name' => $actor->name, 'note' => $note, 'created_at' => now(),
        ]);

        if ($from) {
            Audit::record('status_changed', "Maintenance {$record->reference}: {$from->label()} → {$to->label()}", $record, ['status' => $from->value], ['status' => $to->value]);
        }
    }

    private function notifyTechnician(MaintenanceRecord $record, User $actor): void
    {
        $user = $record->technician_id ? $record->technician()->with('user')->first()?->user : null;

        if ($user && $user->is_active && ! $user->is($actor)) {
            $user->notify(new MaintenanceAssigned($record->loadMissing('asset')));
        }
    }
}
