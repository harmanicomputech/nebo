<?php

namespace App\Enums;

/** Lifecycle of a maintenance job (D51). */
enum MaintenanceStatus: string
{
    case Reported = 'reported';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            default => ucfirst($this->value),
        };
    }

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Reported => [self::Scheduled, self::InProgress, self::Completed, self::Cancelled],
            self::Scheduled => [self::Scheduled, self::InProgress, self::Completed, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reported => 'danger',
            self::Scheduled => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Reported->value, self::Scheduled->value, self::InProgress->value];
    }
}
