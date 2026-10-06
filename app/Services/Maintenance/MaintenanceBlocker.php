<?php

namespace App\Services\Maintenance;

use App\Models\Equipment;
use App\Models\MaintenanceRecord;
use App\Services\Allocation\AvailabilityBlocker;
use Carbon\CarbonInterface;

/**
 * Units with an open maintenance job scheduled in the window can't be
 * allocated for it (D50). Units already out for maintenance are excluded by
 * their status, so only future windows need this.
 */
class MaintenanceBlocker implements AvailabilityBlocker
{
    public function blockedAssetIds(Equipment $equipment, CarbonInterface $from, CarbonInterface $to): array
    {
        return MaintenanceRecord::query()->where('equipment_id', $equipment->id)
            ->windowOverlapping($from, $to)
            ->pluck('asset_id')->unique()->values()->all();
    }
}
