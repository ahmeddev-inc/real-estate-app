<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::SUSPENDED => 'موقوف',
            self::PENDING => 'قيد المراجعة',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::SUSPENDED => 'red',
            self::PENDING => 'yellow',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => self::ACTIVE->label(),
            self::INACTIVE->value => self::INACTIVE->label(),
            self::SUSPENDED->value => self::SUSPENDED->label(),
            self::PENDING->value => self::PENDING->label(),
        ];
    }

    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }
}
