<?php

namespace App\Enums;

enum TripDirection: string
{
    case Outbound = 'outbound';
    case Return = 'return';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'To venue',
            self::Return => 'Return to base',
            self::Transfer => 'Transfer',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Outbound => 'truck',
            self::Return => 'undo-2',
            self::Transfer => 'arrow-left-right',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $d) => [$d->value => $d->label()])->all();
    }
}
