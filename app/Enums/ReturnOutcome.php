<?php

namespace App\Enums;

/** What happened to equipment when it came back (brief §27). */
enum ReturnOutcome: string
{
    case Returned = 'returned';
    case Missing = 'missing';
    case Damaged = 'damaged';
    case NeedsInspection = 'needs_inspection';
    case NeedsMaintenance = 'needs_maintenance';

    public function label(): string
    {
        return match ($this) {
            self::NeedsInspection => 'Needs inspection',
            self::NeedsMaintenance => 'Needs maintenance',
            default => ucfirst($this->value),
        };
    }

    /** Asset status code after check-in. */
    public function assetStatus(): string
    {
        return match ($this) {
            self::Returned => 'available',
            self::Missing => 'lost',
            self::Damaged => 'damaged',
            self::NeedsInspection => 'under_inspection',
            self::NeedsMaintenance => 'maintenance_required',
        };
    }

    /** Condition grade to record, if the outcome implies one. */
    public function condition(): ?string
    {
        return match ($this) {
            self::Damaged => 'damaged',
            self::NeedsInspection => 'requires_inspection',
            default => null,
        };
    }
}
