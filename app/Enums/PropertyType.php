<?php

namespace App\Enums;

enum PropertyType: string
{
    case APARTMENT = 'apartment';
    case VILLA = 'villa';
    case TOWNHOUSE = 'townhouse';
    case DUPLEX = 'duplex';
    case PENTHOUSE = 'penthouse';
    case STUDIO = 'studio';
    case COMMERCIAL = 'commercial';
    case LAND = 'land';
    case BUILDING = 'building';
    case CHALET = 'chalet';

    public function label(): string
    {
        return match($this) {
            self::APARTMENT => 'شقة',
            self::VILLA => 'فيلا',
            self::TOWNHOUSE => 'تاون هاوس',
            self::DUPLEX => 'دوبلكس',
            self::PENTHOUSE => 'بنتهاوس',
            self::STUDIO => 'استوديو',
            self::COMMERCIAL => 'تجاري',
            self::LAND => 'أرض',
            self::BUILDING => 'عمارة',
            self::CHALET => 'شاليه',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::APARTMENT => 'heroicon-o-building-office',
            self::VILLA => 'heroicon-o-home',
            self::TOWNHOUSE => 'heroicon-o-home-modern',
            self::DUPLEX => 'heroicon-o-building-office-2',
            self::PENTHOUSE => 'heroicon-o-building-office',
            self::STUDIO => 'heroicon-o-cube',
            self::COMMERCIAL => 'heroicon-o-building-storefront',
            self::LAND => 'heroicon-o-map',
            self::BUILDING => 'heroicon-o-building-library',
            self::CHALET => 'heroicon-o-home',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::APARTMENT->value => self::APARTMENT->label(),
            self::VILLA->value => self::VILLA->label(),
            self::TOWNHOUSE->value => self::TOWNHOUSE->label(),
            self::DUPLEX->value => self::DUPLEX->label(),
            self::PENTHOUSE->value => self::PENTHOUSE->label(),
            self::STUDIO->value => self::STUDIO->label(),
            self::COMMERCIAL->value => self::COMMERCIAL->label(),
            self::LAND->value => self::LAND->label(),
            self::BUILDING->value => self::BUILDING->label(),
            self::CHALET->value => self::CHALET->label(),
        ];
    }

    public static function residentialTypes(): array
    {
        return [
            self::APARTMENT,
            self::VILLA,
            self::TOWNHOUSE,
            self::DUPLEX,
            self::PENTHOUSE,
            self::STUDIO,
            self::CHALET,
        ];
    }

    public function isResidential(): bool
    {
        return in_array($this, self::residentialTypes());
    }

    public function isCommercial(): bool
    {
        return $this === self::COMMERCIAL;
    }

    public function isLand(): bool
    {
        return $this === self::LAND;
    }
}
