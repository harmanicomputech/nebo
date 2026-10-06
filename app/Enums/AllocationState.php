<?php

namespace App\Enums;

enum AllocationState: string
{
    case Reserved = 'reserved';       // held for the event
    case CheckedOut = 'checked_out';  // dispatched to the event
    case Returned = 'returned';       // checked back in (any outcome)
    case Released = 'released';       // un-allocated before dispatch

    /** States that occupy the hold window. */
    public function isActive(): bool
    {
        return in_array($this, [self::Reserved, self::CheckedOut], true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Reserved->value, self::CheckedOut->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::CheckedOut => 'Checked out',
            default => ucfirst($this->value),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reserved => 'info',
            self::CheckedOut => 'warning',
            self::Returned => 'success',
            self::Released => 'neutral',
        };
    }
}
