<?php

namespace App\Enums;

enum StockBucket: string
{
    case Available = 'available';
    case Quarantine = 'quarantine';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Quarantine => 'Quarantine (damaged / to inspect)',
        };
    }
}
