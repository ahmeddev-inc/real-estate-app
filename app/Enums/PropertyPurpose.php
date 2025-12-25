<?php

namespace App\Enums;

enum PropertyPurpose: string
{
    case SALE = 'sale';
    case RENT = 'rent';
    case BOTH = 'both';

    public function label(): string
    {
        return match($this) {
            self::SALE => 'بيع',
            self::RENT => 'إيجار',
            self::BOTH => 'بيع وإيجار',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::SALE => 'green',
            self::RENT => 'blue',
            self::BOTH => 'purple',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::SALE->value => self::SALE->label(),
            self::RENT->value => self::RENT->label(),
            self::BOTH->value => self::BOTH->label(),
        ];
    }

    public function isForSale(): bool
    {
        return in_array($this, [self::SALE, self::BOTH]);
    }

    public function isForRent(): bool
    {
        return in_array($this, [self::RENT, self::BOTH]);
    }
}
