<?php

namespace App\Enums;

/** Production lifecycle of an event (brief §22). */
enum EventStatus: string
{
    case Planning = 'planning';
    case Confirmed = 'confirmed';
    case InPreparation = 'in_preparation';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::InPreparation => 'In Preparation',
            self::InProgress => 'Live / In Progress',
            self::OnHold => 'On Hold',
            default => ucfirst($this->value),
        };
    }

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Planning => [self::Confirmed, self::OnHold, self::Cancelled],
            self::Confirmed => [self::InPreparation, self::Planning, self::OnHold, self::Cancelled],
            self::InPreparation => [self::InProgress, self::Confirmed, self::OnHold, self::Cancelled],
            self::InProgress => [self::Completed],
            self::OnHold => [self::Planning, self::Confirmed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Events that hold equipment and crew (availability counts only these). */
    public function holdsResources(): bool
    {
        return in_array($this, [self::Planning, self::Confirmed, self::InPreparation, self::InProgress, self::OnHold], true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Planning => 'info',
            self::Confirmed => 'success',
            self::InPreparation => 'warning',
            self::InProgress => 'brand',
            self::Completed => 'dark',
            self::OnHold, self::Cancelled => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planning => 'clipboard-list',
            self::Confirmed => 'circle-check',
            self::InPreparation => 'package-check',
            self::InProgress => 'radio',
            self::Completed => 'check',
            self::OnHold => 'history',
            self::Cancelled => 'circle-x',
        };
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->holdsResources())));
    }
}
