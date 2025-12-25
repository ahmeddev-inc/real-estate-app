<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class BaseController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * إرسال استجابة ناجحة
     */
    protected function success($data = null, string $message = null, int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * إرسال استجابة فاشلة
     */
    protected function error(string $message, int $code = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * إرسال استجابة الصفحات
     */
    protected function paginate($paginator, array $additionalData = [])
    {
        return $this->success(array_merge([
            'data' => $paginator->items(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ], $additionalData));
    }

    /**
     * التحقق من الصلاحيات
     */
    protected function authorizeUser(string $permission)
    {
        if (!auth()->user()->can($permission)) {
            abort(403, 'غير مصرح لك بتنفيذ هذا الإجراء');
        }
    }

    /**
     * التحقق من أن المستخدم هو المالك أو لديه صلاحية
     */
    protected function authorizeOwner($model, string $field = 'user_id')
    {
        $user = auth()->user();
        
        if ($user->is_admin) {
            return true;
        }

        if ($model->$field !== $user->id) {
            abort(403, 'غير مصرح لك بتنفيذ هذا الإجراء');
        }
    }

    /**
     * التحقق من صحة البيانات المدخلة
     */
    protected function validateRequest(array $rules, array $messages = [])
    {
        $validator = validator(request()->all(), $rules, $messages);
        
        if ($validator->fails()) {
            return $this->error('فشل التحقق من صحة البيانات', 422, $validator->errors());
        }

        return null;
    }
}
