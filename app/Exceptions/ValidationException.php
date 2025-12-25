<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException as BaseValidationException;
use Illuminate\Http\JsonResponse;

class ValidationException extends BaseValidationException
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'فشل التحقق من صحة البيانات',
            'errors' => $this->errors(),
            'code' => 422,
        ], 422);
    }

    public static function withMessages(array $errors): self
    {
        $validator = \Validator::make([], []);
        foreach ($errors as $field => $messages) {
            $validator->errors()->add($field, $messages);
        }
        
        return new self($validator);
    }
}
