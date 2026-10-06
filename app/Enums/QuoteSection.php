<?php

namespace App\Enums;

/** Sections a quotation or package is laid out in, in display order. */
enum QuoteSection: string
{
    case Services = 'services';
    case Equipment = 'equipment';
    case Labour = 'labour';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Labour => 'Crew & labour',
            self::Transport => 'Transport & logistics',
            default => ucfirst($this->value),
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
