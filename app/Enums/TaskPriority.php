<?php

namespace App\Enums;

enum TaskPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::LOW => 'منخفض',
            self::MEDIUM => 'متوسط',
            self::HIGH => 'مرتفع',
            self::URGENT => 'عاجل',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'blue',
            self::HIGH => 'orange',
            self::URGENT => 'red',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
