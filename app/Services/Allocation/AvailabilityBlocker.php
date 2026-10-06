<?php

namespace App\Services\Allocation;

use App\Models\Equipment;
use Carbon\CarbonInterface;

/**
 * Something other than an event that makes serialized units unavailable for
 * a window (scheduled maintenance in Phase 6). Register implementations in
 * AvailabilityService::BLOCKERS.
 */
interface AvailabilityBlocker
{
    /**
     * @return list<int> asset ids unavailable in [from, to)
     */
    public function blockedAssetIds(Equipment $equipment, CarbonInterface $from, CarbonInterface $to): array;
}
