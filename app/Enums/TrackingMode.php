<?php

namespace App\Enums;

enum TrackingMode: string
{
    /** Each physical unit is a record with its own tag, status and history. */
    case Serialized = 'serialized';

    /** Counted by quantity per location (cable, consumables, small accessories). */
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Serialized => 'Serialized',
            self::Bulk => 'Quantity',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Serialized => 'Track every unit individually (asset tag, serial number, history).',
            self::Bulk => 'Track a quantity per location (cables, consumables, small accessories).',
        };
    }
}
