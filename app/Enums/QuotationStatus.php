<?php

namespace App\Enums;

/** Lifecycle of a quotation (D60). */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Accepted, self::Declined, self::Expired, self::Draft, self::Cancelled],
            self::Declined, self::Expired => [self::Draft, self::Cancelled],
            self::Accepted, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    /** Waiting on the customer. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Declined => 'danger',
            self::Expired => 'warning',
            self::Cancelled => 'neutral',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
