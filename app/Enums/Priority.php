<?php

namespace App\Enums;

enum Priority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';
    case VIP = 'vip';

    public function label(): string
    {
        return match($this) {
            self::LOW => 'منخفض',
            self::MEDIUM => 'متوسط',
            self::HIGH => 'عالي',
            self::URGENT => 'عاجل',
            self::VIP => 'VIP',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LOW => 'gray',
            self::MEDIUM => 'blue',
            self::HIGH => 'yellow',
            self::URGENT => 'orange',
            self::VIP => 'purple',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::LOW => 'heroicon-o-arrow-down',
            self::MEDIUM => 'heroicon-o-minus',
            self::HIGH => 'heroicon-o-arrow-up',
            self::URGENT => 'heroicon-o-exclamation-triangle',
            self::VIP => 'heroicon-o-star',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::LOW->value => self::LOW->label(),
            self::MEDIUM->value => self::MEDIUM->label(),
            self::HIGH->value => self::HIGH->label(),
            self::URGENT->value => self::URGENT->label(),
            self::VIP->value => self::VIP->label(),
        ];
    }

    public function isHighOrUrgent(): bool
    {
        return in_array($this, [self::HIGH, self::URGENT, self::VIP]);
    }

    public function getFollowUpDays(): int
    {
        return match($this) {
            self::URGENT => 1,
            self::VIP => 2,
            self::HIGH => 3,
            self::MEDIUM => 5,
            self::LOW => 7,
        };
    }
}
