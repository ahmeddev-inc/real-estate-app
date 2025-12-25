<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use App\Exceptions\BusinessException;

class AuthController extends BaseController
{
    /**
     * تسجيل دخول المستخدم
     */
    public function login(Request $request)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }

            // محاولة تسجيل الدخول
            if (!Auth::attempt($request->only('email', 'password'))) {
                throw new BusinessException('البريد الإلكتروني أو كلمة المرور غير صحيحة', 401);
            }

            $user = Auth::user();

            // التحقق من حالة الحساب
            if ($user->status === UserStatus::SUSPENDED) {
                Auth::logout();
                throw new BusinessException('حسابك موقوف. الرجاء التواصل مع الإدارة', 403);
            }

            if ($user->status === UserStatus::INACTIVE) {
                Auth::logout();
                throw new BusinessException('حسابك غير نشط. الرجاء التواصل مع الإدارة', 403);
            }

            if ($user->status === UserStatus::PENDING) {
                Auth::logout();
                throw new BusinessException('حسابك قيد المراجعة. الرجاء الانتظار حتى يتم تفعيله', 403);
            }

            // تحديث آخر تسجيل دخول
            $user->updateLastLogin($request->ip());

            // إنشاء التوكن
            $token = $user->createToken('auth-token')->plainTextToken;

            // إرجاع الاستجابة
            return $this->success([
                'user' => $user->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone', 
                    'role', 'status', 'avatar', 'city', 'commission_rate'
                ]),
                'token' => $token,
                'permissions' => $user->permissions ?? [],
            ], 'تم تسجيل الدخول بنجاح');

        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->error('حدث خطأ في الخادم', 500);
        }
    }

    /**
     * تسجيل خروج المستخدم
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            
            return $this->success(null, 'تم تسجيل الخروج بنجاح');
            
        } catch (\Exception $e) {
            return $this->error('حدث خطأ أثناء تسجيل الخروج', 500);
        }
    }

    /**
     * الحصول على بيانات المستخدم الحالي
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user();
            
            // تحميل العلاقات المطلوبة
            $user->load(['manager', 'assignedClients', 'managedProperties']);
            
            // إحصائيات إضافية حسب الدور
            $stats = [];
            if ($user->is_agent) {
                $stats = [
                    'total_clients' => $user->assignedClients()->count(),
                    'total_properties' => $user->managedProperties()->count(),
                    'active_properties' => $user->managedProperties()->where('status', 'available')->count(),
                ];
            }
            
            return $this->success([
                'user' => array_merge($user->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone', 
                    'national_id', 'role', 'user_type', 'avatar', 'address',
                    'city', 'country', 'status', 'commission_rate', 
                    'employee_id', 'job_title', 'permissions', 'settings',
                    'last_login_at', 'created_at'
                ]), [
                    'full_name' => $user->full_name,
                    'initials' => $user->initials,
                    'is_admin' => $user->is_admin,
                    'is_agent' => $user->is_agent,
                    'is_active' => $user->is_active,
                    'role_label' => $user->role_label,
                    'status_label' => $user->status_label,
                    'manager' => $user->manager ? $user->manager->only(['uuid', 'full_name', 'email', 'phone']) : null,
                ]),
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            return $this->error('حدث خطأ في جلب بيانات المستخدم', 500);
        }
    }

    /**
     * تحديث بيانات الملف الشخصي
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|required|string|max:50',
                'last_name' => 'sometimes|required|string|max:50',
                'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
                'phone' => 'sometimes|required|string|max:20|unique:users,phone,' . $user->id,
                'avatar' => 'sometimes|nullable|string',
                'address' => 'sometimes|nullable|string',
                'city' => 'sometimes|nullable|string|max:50',
                'country' => 'sometimes|nullable|string|max:50',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // تحديث البيانات
            $user->update($request->only([
                'first_name', 'last_name', 'email', 'phone',
                'avatar', 'address', 'city', 'country'
            ]));
            
            return $this->success([
                'user' => $user->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone',
                    'avatar', 'address', 'city', 'country'
                ]),
            ], 'تم تحديث الملف الشخصي بنجاح');
            
        } catch (\Exception $e) {
            return $this->error('حدث خطأ في تحديث الملف الشخصي', 500);
        }
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // التحقق من كلمة المرور الحالية
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->error('كلمة المرور الحالية غير صحيحة', 400);
            }
            
            // تحديث كلمة المرور
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);
            
            // حذف جميع التوكنات الأخرى
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
            
            return $this->success(null, 'تم تغيير كلمة المرور بنجاح');
            
        } catch (\Exception $e) {
            return $this->error('حدث خطأ في تغيير كلمة المرور', 500);
        }
    }

    /**
     * تسجيل مستخدم جديد (للعملاء)
     */
    public function register(Request $request)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'email' => 'required|email|unique:users',
                'phone' => 'required|string|max:20|unique:users',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'city' => 'required|string|max:50',
                'address' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // إنشاء المستخدم
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'client',
                'user_type' => 'client',
                'city' => $request->city,
                'address' => $request->address,
                'status' => 'active',
            ]);
            
            // إنشاء التوكن
            $token = $user->createToken('auth-token')->plainTextToken;
            
            return $this->success([
                'user' => $user->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone', 
                    'role', 'status', 'city'
                ]),
                'token' => $token,
            ], 'تم إنشاء الحساب بنجاح', 201);
            
        } catch (\Exception $e) {
            return $this->error('حدث خطأ في إنشاء الحساب', 500);
        }
    }

    /**
     * طلب إعادة تعيين كلمة المرور
     */
    public function forgotPassword(Request $request)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // البحث عن المستخدم
            $user = User::where('email', $request->email)->first();
            
            // التحقق من حالة الحساب
            if ($user->status !== UserStatus::ACTIVE) {
                return $this->error('الحساب غير نشط', 400);
            }
            
            // إنشاء رمز إعادة التعيين
            $resetToken = \Str::random(60);
            
            // حفظ الرمز في قاعدة البيانات (سيتم إنشاء جدول لاحقاً)
            // \DB::table('password_resets')->updateOrInsert(
            //     ['email' => $user->email],
            //     ['token' => Hash::make($resetToken), 'created_at' => now()]
            // );
            
            // إرسال البريد الإلكتروني (سيتم تنفيذه لاحقاً)
            
            return $this->success(null, 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني');
            
        } catch (\Exception $e) {
            return $this->error('حدث خطأ في طلب إعادة تعيين كلمة المرور', 500);
        }
    }
}
