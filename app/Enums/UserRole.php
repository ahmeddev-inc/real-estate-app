<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case AGENT = 'agent';
    case CLIENT = 'client';
    case OWNER = 'owner';

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'المدير العام',
            self::ADMIN => 'مدير النظام',
            self::MANAGER => 'مدير فرع',
            self::AGENT => 'وسيط عقاري',
            self::CLIENT => 'عميل',
            self::OWNER => 'مالك عقار',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'danger',
            self::ADMIN => 'warning',
            self::MANAGER => 'primary',
            self::AGENT => 'success',
            self::CLIENT => 'info',
            self::OWNER => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::SUPER_ADMIN->value => self::SUPER_ADMIN->label(),
            self::ADMIN->value => self::ADMIN->label(),
            self::MANAGER->value => self::MANAGER->label(),
            self::AGENT->value => self::AGENT->label(),
            self::CLIENT->value => self::CLIENT->label(),
            self::OWNER->value => self::OWNER->label(),
        ];
    }

    public static function options(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }

    public function isAdmin(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN, self::MANAGER]);
    }

    public function canManageProperties(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN, self::MANAGER, self::AGENT]);
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::ADMIN]);
    }
}
