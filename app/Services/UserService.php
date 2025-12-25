<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Exceptions\BusinessException;

class UserService extends BaseService
{
    public function __construct()
    {
        $this->model = User::class;
    }

    /**
     * إنشاء مستخدم جديد
     */
    public function createUser(array $data): User
    {
        return $this->transaction(function () use ($data) {
            // تجهيز كلمة المرور
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // تعيين القيم الافتراضية
            $data['status'] = $data['status'] ?? UserStatus::ACTIVE->value;
            $data['role'] = $data['role'] ?? UserRole::AGENT->value;

            // التحقق من البريد الإلكتروني الفريد
            if (isset($data['email']) && $this->emailExists($data['email'])) {
                throw new BusinessException('البريد الإلكتروني مسجل بالفعل', 400);
            }

            // التحقق من رقم الهاتف الفريد
            if (isset($data['phone']) && $this->phoneExists($data['phone'])) {
                throw new BusinessException('رقم الهاتف مسجل بالفعل', 400);
            }

            // إنشاء المستخدم
            $user = User::create($data);

            // تسجيل الحدث
            $user->logAudit('created', $user->toArray());

            return $user;
        });
    }

    /**
     * تحديث بيانات المستخدم
     */
    public function updateUser(string $uuid, array $data): User
    {
        return $this->transaction(function () use ($uuid, $data) {
            $user = $this->findByUuidOrFail($uuid);

            // تحديث كلمة المرور إذا تم توفيرها
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // التحقق من تفرد البريد الإلكتروني
            if (isset($data['email']) && $data['email'] !== $user->email) {
                if ($this->emailExists($data['email'], $user->id)) {
                    throw new BusinessException('البريد الإلكتروني مسجل بالفعل', 400);
                }
            }

            // التحقق من تفرد رقم الهاتف
            if (isset($data['phone']) && $data['phone'] !== $user->phone) {
                if ($this->phoneExists($data['phone'], $user->id)) {
                    throw new BusinessException('رقم الهاتف مسجل بالفعل', 400);
                }
            }

            // حفظ البيانات القديمة للمراجعة
            $oldData = $user->toArray();

            // تحديث المستخدم
            $user->update($data);

            // تسجيل التغييرات
            $user->logAudit('updated', $user->getChanges(), $oldData);

            return $user->fresh();
        });
    }

    /**
     * تحديث حالة المستخدم
     */
    public function updateUserStatus(string $uuid, UserStatus $status, ?string $reason = null): User
    {
        return $this->transaction(function () use ($uuid, $status, $reason) {
            $user = $this->findByUuidOrFail($uuid);
            
            $oldStatus = $user->status;

            $updateData = ['status' => $status->value];
            
            if ($status === UserStatus::SUSPENDED) {
                $updateData['suspended_at'] = now();
                $updateData['suspension_reason'] = $reason;
            } elseif ($status === UserStatus::ACTIVE) {
                $updateData['suspended_at'] = null;
                $updateData['suspension_reason'] = null;
            }

            $user->update($updateData);

            // تسجيل تغيير الحالة
            $user->logAudit('status_changed', [
                'new_status' => $status->value,
                'old_status' => $oldStatus,
                'reason' => $reason,
            ]);

            return $user->fresh();
        });
    }

    /**
     * تحديث صلاحيات المستخدم
     */
    public function updateUserPermissions(string $uuid, array $permissions): User
    {
        return $this->transaction(function () use ($uuid, $permissions) {
            $user = $this->findByUuidOrFail($uuid);

            // التحقق من صلاحية المستخدم للتعديل
            if ($user->role === UserRole::SUPER_ADMIN) {
                throw new BusinessException('لا يمكن تعديل صلاحيات المدير العام', 403);
            }

            $oldPermissions = $user->permissions ?? [];

            $user->update(['permissions' => $permissions]);

            // تسجيل تغيير الصلاحيات
            $user->logAudit('permissions_updated', [
                'new_permissions' => $permissions,
                'old_permissions' => $oldPermissions,
            ]);

            return $user->fresh();
        });
    }

    /**
     * تحديث آخر تسجيل دخول
     */
    public function updateLastLogin(string $uuid, string $ip): void
    {
        $user = $this->findByUuidOrFail($uuid);
        $user->updateLastLogin($ip);
    }

    /**
     * الحصول على جميع الوكلاء
     */
    public function getAgents(array $filters = [], int $perPage = 15)
    {
        $query = User::where('role', UserRole::AGENT->value);

        // تطبيق الفلاتر
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['city'])) {
            $query->where('city', 'ILIKE', "%{$filters['city']}%");
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('last_name', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('email', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('phone', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('employee_id', 'ILIKE', "%{$filters['search']}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * الحصول على إحصائيات الوسيط
     */
    public function getAgentStats(string $uuid): array
    {
        $user = $this->findByUuidOrFail($uuid);

        if ($user->role !== UserRole::AGENT) {
            throw new BusinessException('المستخدم ليس وسيطاً', 400);
        }

        return [
            'total_properties' => $user->managedProperties()->count(),
            'available_properties' => $user->managedProperties()->where('status', 'available')->count(),
            'sold_properties' => $user->managedProperties()->where('status', 'sold')->count(),
            'rented_properties' => $user->managedProperties()->where('status', 'rented')->count(),
            'total_clients' => $user->assignedClients()->count(),
            'active_clients' => $user->assignedClients()->where('status', 'active')->count(),
            'leads' => $user->assignedClients()->where('status', 'lead')->count(),
            'total_commission' => $this->calculateAgentCommission($user),
            'success_rate' => $this->calculateAgentSuccessRate($user),
        ];
    }

    /**
     * البحث عن مستخدمين
     */
    public function searchUsers(string $search, array $filters = [], int $perPage = 15)
    {
        $query = User::query();

        // تطبيق البحث
        $query->where(function ($q) use ($search) {
            $q->where('first_name', 'ILIKE', "%{$search}%")
              ->orWhere('last_name', 'ILIKE', "%{$search}%")
              ->orWhere('email', 'ILIKE', "%{$search}%")
              ->orWhere('phone', 'ILIKE', "%{$search}%")
              ->orWhere('employee_id', 'ILIKE', "%{$search}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
        });

        // تطبيق الفلاتر
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        return $query->paginate($perPage);
    }

    /**
     * التحقق من وجود البريد الإلكتروني
     */
    private function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $query = User::where('email', $email);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }

    /**
     * التحقق من وجود رقم الهاتف
     */
    private function phoneExists(string $phone, ?int $excludeUserId = null): bool
    {
        $query = User::where('phone', $phone);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }

    /**
     * حساب عمولة الوسيط
     */
    private function calculateAgentCommission(User $agent): float
    {
        // هذا سيتغير عند إضافة نموذج الصفقات
        $commission = 0;

        // حساب العمولة من العقارات المباعة
        $soldProperties = $agent->managedProperties()
            ->where('status', 'sold')
            ->get();

        foreach ($soldProperties as $property) {
            $commission += $property->calculateCommission();
        }

        return $commission;
    }

    /**
     * حساب نسبة نجاح الوسيط
     */
    private function calculateAgentSuccessRate(User $agent): float
    {
        $totalProperties = $agent->managedProperties()->count();
        $successfulProperties = $agent->managedProperties()
            ->whereIn('status', ['sold', 'rented'])
            ->count();

        if ($totalProperties === 0) {
            return 0;
        }

        return ($successfulProperties / $totalProperties) * 100;
    }

    /**
     * الحصول على المستخدمين الذين ليس لديهم مدير
     */
    public function getUsersWithoutManager(array $filters = [])
    {
        $query = User::whereNull('manager_id');

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * تعيين مدير للمستخدم
     */
    public function assignManager(string $userUuid, string $managerUuid): User
    {
        return $this->transaction(function () use ($userUuid, $managerUuid) {
            $user = $this->findByUuidOrFail($userUuid);
            $manager = $this->findByUuidOrFail($managerUuid);

            // التحقق من أن المدير له صلاحية إدارة
            if (!$manager->role->isAdmin()) {
                throw new BusinessException('المستخدم المحدد ليس مديراً', 400);
            }

            // التحقق من عدم تعيين المستخدم كمدير لنفسه
            if ($user->id === $manager->id) {
                throw new BusinessException('لا يمكن تعيين المستخدم كمدير لنفسه', 400);
            }

            $oldManagerId = $user->manager_id;

            $user->update(['manager_id' => $manager->id]);

            // تسجيل التغيير
            $user->logAudit('manager_assigned', [
                'new_manager_id' => $manager->id,
                'old_manager_id' => $oldManagerId,
            ]);

            return $user->fresh();
        });
    }

    /**
     * إلغاء تعيين المدير
     */
    public function removeManager(string $userUuid): User
    {
        return $this->transaction(function () use ($userUuid) {
            $user = $this->findByUuidOrFail($userUuid);

            $oldManagerId = $user->manager_id;

            $user->update(['manager_id' => null]);

            // تسجيل التغيير
            $user->logAudit('manager_removed', [
                'old_manager_id' => $oldManagerId,
            ]);

            return $user->fresh();
        });
    }

    /**
     * الحصول على المرؤوسين
     */
    public function getSubordinates(string $managerUuid, array $filters = [])
    {
        $manager = $this->findByUuidOrFail($managerUuid);

        $query = $manager->subordinates();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('last_name', 'ILIKE', "%{$filters['search']}%")
                  ->orWhere('email', 'ILIKE', "%{$filters['search']}%");
            });
        }

        return $query->get();
    }
}
