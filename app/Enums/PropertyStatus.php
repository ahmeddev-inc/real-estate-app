<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case DRAFT = 'draft';
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case RENTED = 'rented';
    case UNDER_CONTRACT = 'under_contract';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'مسودة',
            self::AVAILABLE => 'متاح',
            self::RESERVED => 'محجوز',
            self::SOLD => 'مباع',
            self::RENTED => 'مؤجر',
            self::UNDER_CONTRACT => 'قيد التعاقد',
            self::INACTIVE => 'غير نشط',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::AVAILABLE => 'green',
            self::RESERVED => 'yellow',
            self::SOLD => 'red',
            self::RENTED => 'blue',
            self::UNDER_CONTRACT => 'purple',
            self::INACTIVE => 'gray',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DRAFT => 'heroicon-o-document-text',
            self::AVAILABLE => 'heroicon-o-check-circle',
            self::RESERVED => 'heroicon-o-clock',
            self::SOLD => 'heroicon-o-check-badge',
            self::RENTED => 'heroicon-o-key',
            self::UNDER_CONTRACT => 'heroicon-o-document-duplicate',
            self::INACTIVE => 'heroicon-o-x-circle',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->label(),
            self::AVAILABLE->value => self::AVAILABLE->label(),
            self::RESERVED->value => self::RESERVED->label(),
            self::SOLD->value => self::SOLD->label(),
            self::RENTED->value => self::RENTED->label(),
            self::UNDER_CONTRACT->value => self::UNDER_CONTRACT->label(),
            self::INACTIVE->value => self::INACTIVE->label(),
        ];
    }

    public function isAvailable(): bool
    {
        return $this === self::AVAILABLE;
    }

    public function isSoldOrRented(): bool
    {
        return in_array($this, [self::SOLD, self::RENTED]);
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [self::DRAFT, self::AVAILABLE, self::INACTIVE]);
    }
}
