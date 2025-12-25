<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case AGENT = 'agent';
    case CLIENT = 'client';
    case OWNER = 'owner';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'مدير النظام',
            self::MANAGER => 'مدير',
            self::AGENT => 'وسيط عقاري',
            self::CLIENT => 'عميل',
            self::OWNER => 'مالك عقار',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
