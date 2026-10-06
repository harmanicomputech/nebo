<?php

namespace App\Services\Maintenance;

use App\Enums\MaintenanceStatus;
use App\Models\Equipment;
use App\Models\EquipmentAsset;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Support\Lookups;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Recurring maintenance (D52). One schedule per unit and type; the asset's
 * next_maintenance_due_on always mirrors its earliest active schedule.
 */
class MaintenanceScheduler
{
    public function __construct(private Lookups $lookups) {}

    /**
     * @param  array{type: string, interval_days: int, next_due_on: string, notes?: ?string}  $data
     */
    public function create(User $actor, EquipmentAsset $asset, array $data): MaintenanceSchedule
    {
        $this->assertType($data['type']);
        $asset->loadMissing('status');

        if ($asset->trashed() || $asset->status->group->isTerminal()) {
            throw ValidationException::withMessages(['asset' => "{$asset->asset_tag} is archived, lost or retired."]);
        }

        if ($asset->maintenanceSchedules()->where('type', $data['type'])->exists()) {
            throw ValidationException::withMessages(['type' => "{$asset->asset_tag} already has a ".$this->lookups->label('maintenance_type', $data['type']).' schedule. Edit that one instead.']);
        }

        return DB::transaction(function () use ($actor, $asset, $data) {
            $schedule = $asset->maintenanceSchedules()->create([
                'type' => $data['type'], 'interval_days' => $data['interval_days'], 'next_due_on' => $data['next_due_on'],
                'notes' => $data['notes'] ?? null, 'is_active' => true, 'created_by' => $actor->id,
            ]);
            $this->syncAssetDue($asset);

            return $schedule;
        });
    }

    /**
     * Adds the schedule to every in-service unit of an item that doesn't have
     * one of this type yet.
     *
     * @param  array{type: string, interval_days: int, next_due_on: string, notes?: ?string}  $data
     * @return int schedules created
     */
    public function createForEquipment(User $actor, Equipment $equipment, array $data): int
    {
        $this->assertType($data['type']);

        $assets = $equipment->assets()->with('status')
            ->whereHas('status', fn ($q) => $q->whereNotIn('group', ['retired', 'lost']))
            ->whereDoesntHave('maintenanceSchedules', fn ($q) => $q->where('type', $data['type']))
            ->get();

        DB::transaction(fn () => $assets->each(fn (EquipmentAsset $a) => $this->create($actor, $a, $data)));

        return $assets->count();
    }

    /**
     * @param  array{interval_days: int, next_due_on: string, notes?: ?string, is_active: bool}  $data
     */
    public function update(MaintenanceSchedule $schedule, array $data): void
    {
        DB::transaction(function () use ($schedule, $data) {
            $schedule->update([
                'interval_days' => $data['interval_days'], 'next_due_on' => $data['next_due_on'],
                'notes' => $data['notes'] ?? null, 'is_active' => $data['is_active'], 'last_reminded_on' => null,
            ]);
            $this->syncAssetDue($schedule->loadMissing('asset')->asset);
        });
    }

    /** Opens a job for a due schedule (one open job per schedule). */
    public function openJob(User $actor, MaintenanceSchedule $schedule): MaintenanceRecord
    {
        $existing = $schedule->records()->open()->first();
        if ($existing) {
            throw ValidationException::withMessages(['schedule' => "{$existing->reference} is already open for this schedule."]);
        }

        return app(MaintenanceService::class)->report($actor, $schedule->loadMissing('asset')->asset, [
            'type' => $schedule->type,
            'issue' => $schedule->typeLabel().' due '.$schedule->next_due_on->format('j M Y'),
            'description' => $schedule->notes,
            'source' => 'schedule',
            'schedule_id' => $schedule->id,
            'out_of_service' => false,
        ]);
    }

    /**
     * A completed job advances its schedule (or the unit's schedule of the
     * same type). An explicit next due date overrides the interval.
     */
    public function recordDone(MaintenanceRecord $record, ?string $nextDueOn = null): void
    {
        $today = CarbonImmutable::now(config('nebo.display_timezone'))->startOfDay();
        $schedule = $record->schedule_id
            ? MaintenanceSchedule::find($record->schedule_id)
            : MaintenanceSchedule::where('asset_id', $record->asset_id)->where('type', $record->type)->where('is_active', true)->first();

        if ($schedule) {
            $schedule->update([
                'last_done_on' => $today->toDateString(),
                'next_due_on' => $nextDueOn ?: $today->addDays($schedule->interval_days)->toDateString(),
                'last_reminded_on' => null,
            ]);
        }

        $asset = EquipmentAsset::withTrashed()->findOrFail($record->asset_id);
        if ($nextDueOn && ! $schedule) {
            $asset->update(['next_maintenance_due_on' => $nextDueOn]);
        } else {
            $this->syncAssetDue($asset);
        }
    }

    public function syncAssetDue(EquipmentAsset $asset): void
    {
        if (! $asset->maintenanceSchedules()->exists()) {
            return; // keep a manually entered date
        }

        $next = $asset->maintenanceSchedules()->where('is_active', true)->min('next_due_on');
        $asset->update(['next_maintenance_due_on' => $next]);
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, $this->lookups->activeKeys('maintenance_type'), true)) {
            throw ValidationException::withMessages(['type' => 'Choose a maintenance type.']);
        }
    }

    /** Open jobs keep their schedule from being reported as "due" twice. */
    public static function hasOpenJob(MaintenanceSchedule $schedule): bool
    {
        return $schedule->records()->whereIn('status', MaintenanceStatus::openValues())->exists();
    }
}
