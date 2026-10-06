<?php

namespace App\Enums;

/**
 * What an asset status means to the system. Statuses themselves are data
 * (asset_statuses); every status belongs to one of these groups, and
 * dashboards, availability and reports work from the group.
 */
enum AssetStatusGroup: string
{
    case Available = 'available';     // in the warehouse, ready to go
    case Committed = 'committed';     // reserved or allocated to an event
    case Out = 'out';                 // checked out, in transit, deployed, on site
    case Attention = 'attention';     // inspection or maintenance
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Retired = 'retired';
    case Unavailable = 'unavailable'; // held back for any other reason

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Committed => 'Reserved / allocated',
            self::Out => 'Out on events',
            self::Attention => 'Inspection & maintenance',
            self::Damaged => 'Damaged',
            self::Lost => 'Lost / missing',
            self::Retired => 'Retired',
            self::Unavailable => 'Unavailable',
        };
    }

    /** Lost and retired assets leave the fleet; changing them back needs inventory.archive. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Lost, self::Retired], true);
    }
}
