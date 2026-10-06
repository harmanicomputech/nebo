<?php

namespace App\Enums;

/** Load list and load list item progress (brief §26). */
enum LoadStatus: string
{
    case Pending = 'pending';
    case Picked = 'picked';
    case Loaded = 'loaded';
    case Checked = 'checked';
    case Dispatched = 'dispatched'; // list only

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Picked => 'info',
            self::Loaded => 'warning',
            self::Checked => 'success',
            self::Dispatched => 'dark',
        };
    }

    /**
     * Statuses an item can be set to.
     *
     * @return list<self>
     */
    public static function itemStatuses(): array
    {
        return [self::Pending, self::Picked, self::Loaded, self::Checked];
    }
}
