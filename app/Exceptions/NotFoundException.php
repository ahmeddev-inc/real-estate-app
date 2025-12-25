<?php

namespace App\Exceptions;

class NotFoundException extends BusinessException
{
    public function __construct(string $message = 'المورد غير موجود')
    {
        parent::__construct($message, 404);
    }

    public static function resource(string $resource): self
    {
        return new self("{$resource} غير موجود");
    }

    public static function user(): self
    {
        return new self('المستخدم غير موجود');
    }

    public static function property(): self
    {
        return new self('العقار غير موجود');
    }

    public static function client(): self
    {
        return new self('العميل غير موجود');
    }
}
