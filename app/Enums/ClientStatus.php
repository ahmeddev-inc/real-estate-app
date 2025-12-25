<?php

namespace App\Enums;

enum ClientStatus: string
{
    case LEAD = 'lead';
    case PROSPECT = 'prospect';
    case CLIENT = 'client';
    case INACTIVE = 'inactive';
    case BLACKLISTED = 'blacklisted';

    public function label(): string
    {
        return match($this) {
            self::LEAD => 'ليدر',
            self::PROSPECT => 'محتمل',
            self::CLIENT => 'عميل',
            self::INACTIVE => 'غير نشط',
            self::BLACKLISTED => 'محظور',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LEAD => 'blue',
            self::PROSPECT => 'yellow',
            self::CLIENT => 'green',
            self::INACTIVE => 'gray',
            self::BLACKLISTED => 'red',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::LEAD => 'heroicon-o-user-plus',
            self::PROSPECT => 'heroicon-o-user-circle',
            self::CLIENT => 'heroicon-o-user-check',
            self::INACTIVE => 'heroicon-o-user-minus',
            self::BLACKLISTED => 'heroicon-o-user-x-mark',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::LEAD->value => self::LEAD->label(),
            self::PROSPECT->value => self::PROSPECT->label(),
            self::CLIENT->value => self::CLIENT->label(),
            self::INACTIVE->value => self::INACTIVE->label(),
            self::BLACKLISTED->value => self::BLACKLISTED->label(),
        ];
    }

    public function isActive(): bool
    {
        return in_array($this, [self::LEAD, self::PROSPECT, self::CLIENT]);
    }

    public function canBeContacted(): bool
    {
        return !in_array($this, [self::INACTIVE, self::BLACKLISTED]);
    }

    public function nextStatus(): ?self
    {
        return match($this) {
            self::LEAD => self::PROSPECT,
            self::PROSPECT => self::CLIENT,
            default => null,
        };
    }
}
