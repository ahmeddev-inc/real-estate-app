<?php

namespace App\Enums;

enum ClientType: string
{
    case BUYER = 'buyer';
    case SELLER = 'seller';
    case TENANT = 'tenant';
    case LANDLORD = 'landlord';
    case BOTH = 'both';

    public function label(): string
    {
        return match($this) {
            self::BUYER => 'مشتري',
            self::SELLER => 'بائع',
            self::TENANT => 'مستأجر',
            self::LANDLORD => 'مالك',
            self::BOTH => 'مشتري وبائع',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::BUYER => 'heroicon-o-shopping-cart',
            self::SELLER => 'heroicon-o-currency-dollar',
            self::TENANT => 'heroicon-o-key',
            self::LANDLORD => 'heroicon-o-home',
            self::BOTH => 'heroicon-o-arrows-right-left',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::BUYER->value => self::BUYER->label(),
            self::SELLER->value => self::SELLER->label(),
            self::TENANT->value => self::TENANT->label(),
            self::LANDLORD->value => self::LANDLORD->label(),
            self::BOTH->value => self::BOTH->label(),
        ];
    }

    public function isBuyer(): bool
    {
        return in_array($this, [self::BUYER, self::BOTH]);
    }

    public function isSeller(): bool
    {
        return in_array($this, [self::SELLER, self::BOTH]);
    }
}
