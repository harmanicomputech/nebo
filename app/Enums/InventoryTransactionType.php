<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Added = 'added';                     // registered into inventory
    case Purchased = 'purchased';             // new stock bought
    case StatusChanged = 'status_changed';
    case ConditionChanged = 'condition_changed';
    case Transferred = 'transferred';
    case Adjusted = 'adjusted';               // stock count correction
    case Quarantined = 'quarantined';         // bulk: available → quarantine
    case Released = 'released';               // bulk: quarantine → available
    case WrittenOff = 'written_off';          // bulk: removed (lost / scrapped)
    case Reserved = 'reserved';               // Phase 5
    case Allocated = 'allocated';             // Phase 5
    case CheckedOut = 'checked_out';          // Phase 5
    case Returned = 'returned';               // Phase 5
    case Repaired = 'repaired';               // Phase 6
    case Retired = 'retired';
    case Archived = 'archived';
    case Restored = 'restored';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public function icon(): string
    {
        return match ($this) {
            self::Added, self::Purchased => 'package-check',
            self::Transferred => 'truck',
            self::ConditionChanged => 'clipboard-check',
            self::Adjusted => 'sliders-horizontal',
            self::Quarantined, self::WrittenOff => 'triangle-alert',
            self::Retired, self::Archived => 'archive',
            default => 'history',
        };
    }
}
