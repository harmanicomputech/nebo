<?php

namespace App\Services\Allocation;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Availability of one equipment item for one time window.
 *
 * total      serviceable units in the fleet (or owned available stock)
 * available  units free for the window (not held by other events, not blocked)
 * held       units held by other events in the window
 * blocked    serialized units blocked for the window (e.g. scheduled maintenance)
 * conflicts  other events holding units: list of ['event' => Event, 'quantity' => int]
 * assetIds   free serialized asset ids (empty for bulk)
 */
final readonly class Availability
{
    /**
     * @param  Collection<int, array{event: Event, quantity: int}>  $conflicts
     * @param  list<int>  $assetIds
     */
    public function __construct(
        public int $total,
        public int $available,
        public int $held,
        public int $blocked,
        public Collection $conflicts,
        public array $assetIds = [],
    ) {}
}
