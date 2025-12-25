<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class BusinessException extends Exception
{
    protected $errors;
    protected $statusCode;

    public function __construct(string $message = '', int $statusCode = 400, array $errors = [])
    {
        parent::__construct($message);
        
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'code' => $this->getCode() ?: $this->statusCode,
        ], $this->statusCode);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function validationFailed(array $errors): self
    {
        return new self('فشل التحقق من صحة البيانات', 422, $errors);
    }

    public static function notFound(string $resource = 'المورد'): self
    {
        return new self("{$resource} غير موجود", 404);
    }

    public static function unauthorized(string $message = 'غير مصرح لك بالوصول'): self
    {
        return new self($message, 401);
    }

    public static function forbidden(string $message = 'غير مصرح لك بتنفيذ هذا الإجراء'): self
    {
        return new self($message, 403);
    }

    public static function badRequest(string $message = 'طلب غير صالح'): self
    {
        return new self($message, 400);
    }

    public static function serverError(string $message = 'حدث خطأ في الخادم'): self
    {
        return new self($message, 500);
    }
}
