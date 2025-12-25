<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\User;
use App\Services\UserService;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

class UserController extends BaseController
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * الحصول على قائمة المستخدمين
     */
    public function index(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_users');
            
            $perPage = $request->input('per_page', 15);
            $filters = $request->only(['role', 'status', 'city', 'search']);
            
            $users = $this->userService->searchUsers(
                $filters['search'] ?? '',
                array_filter($filters),
                $perPage
            );
            
            return $this->paginate($users);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على بيانات مستخدم محدد
     */
    public function show($uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_users');
            
            $user = $this->userService->findByUuidOrFail($uuid, ['manager', 'subordinates']);
            
            return $this->success([
                'user' => array_merge($user->toArray(), [
                    'full_name' => $user->full_name,
                    'initials' => $user->initials,
                    'is_admin' => $user->is_admin,
                    'is_agent' => $user->is_agent,
                    'is_active' => $user->is_active,
                    'role_label' => $user->role_label,
                    'status_label' => $user->status_label,
                ]),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * إنشاء مستخدم جديد
     */
    public function store(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('create_users');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'email' => 'required|email|unique:users',
                'phone' => 'required|string|max:20|unique:users',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'role' => 'required|in:' . implode(',', UserRole::values()),
                'user_type' => 'required|in:employee,freelancer,owner,client',
                'city' => 'required|string|max:50',
                'status' => 'sometimes|in:' . implode(',', UserStatus::values()),
                'commission_rate' => 'sometimes|numeric|min:0|max:100',
                'employee_id' => 'sometimes|nullable|string|max:20|unique:users',
                'job_title' => 'sometimes|nullable|string|max:100',
                'manager_id' => 'sometimes|nullable|exists:users,id',
                'avatar' => 'sometimes|nullable|string',
                'address' => 'sometimes|nullable|string',
                'national_id' => 'sometimes|nullable|string|max:20|unique:users',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // إنشاء المستخدم
            $data = $request->all();
            $data['created_by'] = auth()->id();
            
            $user = $this->userService->createUser($data);
            
            return $this->success([
                'user' => $user->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone',
                    'role', 'user_type', 'status', 'city', 'employee_id',
                    'job_title', 'commission_rate'
                ]),
            ], 'تم إنشاء المستخدم بنجاح', 201);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تحديث بيانات مستخدم
     */
    public function update(Request $request, $uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('update_users');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|required|string|max:50',
                'last_name' => 'sometimes|required|string|max:50',
                'email' => 'sometimes|required|email',
                'phone' => 'sometimes|required|string|max:20',
                'role' => 'sometimes|in:' . implode(',', UserRole::values()),
                'user_type' => 'sometimes|in:employee,freelancer,owner,client',
                'city' => 'sometimes|string|max:50',
                'status' => 'sometimes|in:' . implode(',', UserStatus::values()),
                'commission_rate' => 'sometimes|numeric|min:0|max:100',
                'employee_id' => 'sometimes|nullable|string|max:20',
                'job_title' => 'sometimes|nullable|string|max:100',
                'manager_id' => 'sometimes|nullable|exists:users,id',
                'avatar' => 'sometimes|nullable|string',
                'address' => 'sometimes|nullable|string',
                'national_id' => 'sometimes|nullable|string|max:20',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // تحديث المستخدم
            $user = $this->userService->updateUser($uuid, $request->all());
            
            return $this->success([
                'user' => $user->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone',
                    'role', 'user_type', 'status', 'city', 'employee_id',
                    'job_title', 'commission_rate'
                ]),
            ], 'تم تحديث المستخدم بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * حذف مستخدم
     */
    public function destroy($uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('delete_users');
            
            // لا يمكن حذف المدير العام
            $user = $this->userService->findByUuidOrFail($uuid);
            if ($user->role === UserRole::SUPER_ADMIN) {
                return $this->error('لا يمكن حذف المدير العام', 403);
            }
            
            // لا يمكن حذف المستخدم الحالي
            if ($user->id === auth()->id()) {
                return $this->error('لا يمكن حذف حسابك الخاص', 403);
            }
            
            $this->userService->deleteByUuid($uuid);
            
            return $this->success(null, 'تم حذف المستخدم بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تغيير حالة المستخدم
     */
    public function updateStatus(Request $request, $uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('update_users');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:' . implode(',', UserStatus::values()),
                'reason' => 'sometimes|nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            // لا يمكن تغيير حالة المدير العام
            $user = $this->userService->findByUuidOrFail($uuid);
            if ($user->role === UserRole::SUPER_ADMIN && auth()->user()->role !== UserRole::SUPER_ADMIN) {
                return $this->error('لا يمكن تغيير حالة المدير العام', 403);
            }
            
            // لا يمكن تعليق المستخدم الحالي
            if ($user->id === auth()->id() && $request->status === UserStatus::SUSPENDED->value) {
                return $this->error('لا يمكن تعليق حسابك الخاص', 403);
            }
            
            $status = UserStatus::from($request->status);
            $user = $this->userService->updateUserStatus($uuid, $status, $request->reason);
            
            return $this->success([
                'user' => [
                    'uuid' => $user->uuid,
                    'status' => $user->status,
                    'status_label' => $user->status_label,
                ],
            ], 'تم تغيير حالة المستخدم بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تحديث صلاحيات المستخدم
     */
    public function updatePermissions(Request $request, $uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('update_user_permissions');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'permissions' => 'required|array',
                'permissions.*' => 'string',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $user = $this->userService->updateUserPermissions($uuid, $request->permissions);
            
            return $this->success([
                'user' => [
                    'uuid' => $user->uuid,
                    'permissions' => $user->permissions,
                ],
            ], 'تم تحديث صلاحيات المستخدم بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على جميع الوكلاء
     */
    public function agents(Request $request)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_users');
            
            $perPage = $request->input('per_page', 15);
            $filters = $request->only(['status', 'city', 'search']);
            
            $agents = $this->userService->getAgents($filters, $perPage);
            
            // تنسيق البيانات
            $formattedAgents = $agents->map(function ($agent) {
                return array_merge($agent->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone',
                    'status', 'city', 'commission_rate', 'employee_id',
                    'job_title', 'created_at'
                ]), [
                    'full_name' => $agent->full_name,
                    'status_label' => $agent->status_label,
                    'initials' => $agent->initials,
                ]);
            });
            
            return $this->paginate($agents->setCollection($formattedAgents));
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على إحصائيات الوسيط
     */
    public function agentStats($uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_users');
            
            $stats = $this->userService->getAgentStats($uuid);
            
            return $this->success([
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * تعيين مدير للمستخدم
     */
    public function assignManager(Request $request, $userUuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('update_users');
            
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'manager_uuid' => 'required|exists:users,uuid',
            ]);
            
            if ($validator->fails()) {
                return $this->error('بيانات غير صالحة', 422, $validator->errors());
            }
            
            $user = $this->userService->assignManager($userUuid, $request->manager_uuid);
            
            return $this->success([
                'user' => [
                    'uuid' => $user->uuid,
                    'full_name' => $user->full_name,
                    'manager' => $user->manager ? [
                        'uuid' => $user->manager->uuid,
                        'full_name' => $user->manager->full_name,
                    ] : null,
                ],
            ], 'تم تعيين المدير بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * إلغاء تعيين المدير
     */
    public function removeManager($uuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('update_users');
            
            $user = $this->userService->removeManager($uuid);
            
            return $this->success([
                'user' => [
                    'uuid' => $user->uuid,
                    'full_name' => $user->full_name,
                    'manager' => null,
                ],
            ], 'تم إلغاء تعيين المدير بنجاح');
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * الحصول على المرؤوسين
     */
    public function subordinates($managerUuid)
    {
        try {
            // التحقق من الصلاحيات
            $this->authorizeUser('view_users');
            
            $subordinates = $this->userService->getSubordinates($managerUuid);
            
            // تنسيق البيانات
            $formattedSubordinates = $subordinates->map(function ($subordinate) {
                return array_merge($subordinate->only([
                    'uuid', 'first_name', 'last_name', 'email', 'phone',
                    'role', 'status', 'city', 'job_title', 'employee_id',
                    'created_at'
                ]), [
                    'full_name' => $subordinate->full_name,
                    'role_label' => $subordinate->role_label,
                    'status_label' => $subordinate->status_label,
                ]);
            });
            
            return $this->success([
                'subordinates' => $formattedSubordinates,
                'count' => $subordinates->count(),
            ]);
            
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }
}
