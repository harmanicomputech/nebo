<?php

namespace App\Enums;

/** Lifecycle of a logistics trip (D56). */
enum TripStatus: string
{
    case Planned = 'planned';
    case Loading = 'loading';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InTransit => 'In Transit',
            default => ucfirst($this->value),
        };
    }

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Planned => [self::Loading, self::InTransit, self::Cancelled],
            self::Loading => [self::InTransit, self::Planned, self::Cancelled],
            self::InTransit => [self::Arrived],
            self::Arrived, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Trips that still occupy their vehicle and people. */
    public function isActive(): bool
    {
        return in_array($this, [self::Planned, self::Loading, self::InTransit], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isActive();
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Loading => 'warning',
            self::InTransit => 'brand',
            self::Arrived => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Planned->value, self::Loading->value, self::InTransit->value];
    }
}
