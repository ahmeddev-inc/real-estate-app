<?php

namespace App\Enums;

enum PropertyType: string
{
    case APARTMENT = 'apartment';
    case VILLA = 'villa';
    case TOWNHOUSE = 'townhouse';
    case DUPLEX = 'duplex';
    case LAND = 'land';
    case COMMERCIAL = 'commercial';
    case CHALET = 'chalet';

    public function label(): string
    {
        return match($this) {
            self::APARTMENT => 'شقة',
            self::VILLA => 'فيلا',
            self::TOWNHOUSE => 'تاونهوس',
            self::DUPLEX => 'دوبلكس',
            self::LAND => 'أرض',
            self::COMMERCIAL => 'عقار تجاري',
            self::CHALET => 'شاليه',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
