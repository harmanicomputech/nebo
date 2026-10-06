<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Active = 'active';
    case OutOfService = 'out_of_service';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::OutOfService => 'Out of service',
            default => ucfirst($this->value),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OutOfService => 'warning',
            self::Retired => 'neutral',
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
