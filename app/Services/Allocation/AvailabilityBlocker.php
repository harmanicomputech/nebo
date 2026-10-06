<?php

namespace App\Services\Allocation;

use App\Models\Equipment;
use Carbon\CarbonInterface;

/**
 * Something other than an event that makes serialized units unavailable for
 * a window (e.g. scheduled maintenance). Register implementations in
 * AvailabilityService::BLOCKERS.
 */
interface AvailabilityBlocker
{
    /**
     * @return list<int> asset ids unavailable in [from, to)
     */
    public function blockedAssetIds(Equipment $equipment, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * Every blocking window for these items in [from, to), in one query, for
     * day-by-day grids.
     *
     * @param  list<int>  $equipmentIds
     * @return list<array{equipment_id: int, asset_id: int, starts: CarbonInterface, ends: CarbonInterface}>
     */
    public function windows(array $equipmentIds, CarbonInterface $from, CarbonInterface $to): array;
}
