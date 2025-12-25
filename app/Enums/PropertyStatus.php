<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case DRAFT = 'draft';
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case RENTED = 'rented';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'مسودة',
            self::AVAILABLE => 'متاح',
            self::RESERVED => 'محجوز',
            self::SOLD => 'مباع',
            self::RENTED => 'مؤجر',
            self::INACTIVE => 'غير نشط',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
