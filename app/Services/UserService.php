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
    /**
     * Initialize the service with User model
     */
    public function __construct()
    {
        $this->model = User::class;
    }

    /**
     * Create a new user with validation and auditing
     *
     * @param array $data User data including name, email, password, etc.
     * @return \App\Models\User The created user instance
     * @throws \App\Exceptions\BusinessException If email or phone already exists
     */
    public function createUser(array $data): User
    {
        return $this->transaction(function () use ($data) {
            // Prepare password
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Set default values
            $data['status'] = $data['status'] ?? UserStatus::ACTIVE->value;
            $data['role'] = $data['role'] ?? UserRole::AGENT->value;

            // Validate unique email
            if (isset($data['email']) && $this->emailExists($data['email'])) {
                throw new BusinessException('البريد الإلكتروني مسجل بالفعل', 400);
            }

            // Validate unique phone
            if (isset($data['phone']) && $this->phoneExists($data['phone'])) {
                throw new BusinessException('رقم الهاتف مسجل بالفعل', 400);
            }

            // Create user
            $user = User::create($data);

            // Log audit event
            $user->logAudit('created', $user->toArray());

            return $user;
        });
    }

    /**
     * Update an existing user's information
     *
     * @param string $uuid User UUID
     * @param array $data Updated user data
     * @return \App\Models\User The updated user instance
     * @throws \App\Exceptions\BusinessException If email or phone already exists
     */
    public function updateUser(string $uuid, array $data): User
    {
        return $this->transaction(function () use ($uuid, $data) {
            $user = $this->findByUuidOrFail($uuid);

            // Update password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Validate unique email
            if (isset($data['email']) && $data['email'] !== $user->email) {
                if ($this->emailExists($data['email'], $user->id)) {
                    throw new BusinessException('البريد الإلكتروني مسجل بالفعل', 400);
                }
            }

            // Validate unique phone
            if (isset($data['phone']) && $data['phone'] !== $user->phone) {
                if ($this->phoneExists($data['phone'], $user->id)) {
                    throw new BusinessException('رقم الهاتف مسجل بالفعل', 400);
                }
            }

            // Save old data for audit
            $oldData = $user->toArray();

            // Update user
            $user->update($data);

            // Log changes
            $user->logAudit('updated', $user->getChanges(), $oldData);

            return $user->fresh();
        });
    }

    /**
     * Update user status (active, suspended, etc.)
     *
     * @param string $uuid User UUID
     * @param \App\Enums\UserStatus $status New status
     * @param string|null $reason Reason for status change
     * @return \App\Models\User Updated user instance
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

            // Log status change
            $user->logAudit('status_changed', [
                'new_status' => $status->value,
                'old_status' => $oldStatus,
                'reason' => $reason,
            ]);

            return $user->fresh();
        });
    }

    /**
     * Update user permissions
     *
     * @param string $uuid User UUID
     * @param array $permissions New permissions array
     * @return \App\Models\User Updated user instance
     * @throws \App\Exceptions\BusinessException If trying to modify super admin
     */
    public function updateUserPermissions(string $uuid, array $permissions): User
    {
        return $this->transaction(function () use ($uuid, $permissions) {
            $user = $this->findByUuidOrFail($uuid);

            // Check if user can be modified
            if ($user->role === UserRole::SUPER_ADMIN) {
                throw new BusinessException('لا يمكن تعديل صلاحيات المدير العام', 403);
            }

            $oldPermissions = $user->permissions ?? [];

            $user->update(['permissions' => $permissions]);

            // Log permission change
            $user->logAudit('permissions_updated', [
                'new_permissions' => $permissions,
                'old_permissions' => $oldPermissions,
            ]);

            return $user->fresh();
        });
    }

    /**
     * Update last login timestamp and IP
     *
     * @param string $uuid User UUID
     * @param string $ip IP address of login
     * @return void
     */
    public function updateLastLogin(string $uuid, string $ip): void
    {
        $user = $this->findByUuidOrFail($uuid);
        $user->updateLastLogin($ip);
    }

    /**
     * Get all agents with filtering and pagination
     *
     * @param array $filters Filters array [status, city, search]
     * @param int $perPage Number of items per page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAgents(array $filters = [], int $perPage = 15)
    {
        $query = User::where('role', UserRole::AGENT->value);

        // Apply filters
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
     * Get agent statistics (properties, clients, commissions)
     *
     * @param string $uuid Agent UUID
     * @return array Agent statistics
     * @throws \App\Exceptions\BusinessException If user is not an agent
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
     * Search users with filters
     *
     * @param string $search Search term
     * @param array $filters Additional filters [role, status, city]
     * @param int $perPage Number of items per page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function searchUsers(string $search, array $filters = [], int $perPage = 15)
    {
        $query = User::query();

        // Apply search
        $query->where(function ($q) use ($search) {
            $q->where('first_name', 'ILIKE', "%{$search}%")
              ->orWhere('last_name', 'ILIKE', "%{$search}%")
              ->orWhere('email', 'ILIKE', "%{$search}%")
              ->orWhere('phone', 'ILIKE', "%{$search}%")
              ->orWhere('employee_id', 'ILIKE', "%{$search}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
        });

        // Apply filters
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
     * Check if email already exists
     *
     * @param string $email Email to check
     * @param int|null $excludeUserId User ID to exclude (for updates)
     * @return bool True if email exists
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
     * Check if phone already exists
     *
     * @param string $phone Phone to check
     * @param int|null $excludeUserId User ID to exclude (for updates)
     * @return bool True if phone exists
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
     * Calculate total commission for agent
     *
     * @param \App\Models\User $agent Agent instance
     * @return float Total commission amount
     */
    private function calculateAgentCommission(User $agent): float
    {
        // This will change when deal model is added
        $commission = 0;

        // Calculate commission from sold properties
        $soldProperties = $agent->managedProperties()
            ->where('status', 'sold')
            ->get();

        foreach ($soldProperties as $property) {
            $commission += $property->calculateCommission();
        }

        return $commission;
    }

    /**
     * Calculate agent success rate
     *
     * @param \App\Models\User $agent Agent instance
     * @return float Success rate percentage
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
     * Get users without manager assignment
     *
     * @param array $filters Additional filters [role, status]
     * @return \Illuminate\Database\Eloquent\Collection
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
     * Assign manager to user
     *
     * @param string $userUuid User UUID
     * @param string $managerUuid Manager UUID
     * @return \App\Models\User Updated user instance
     * @throws \App\Exceptions\BusinessException If manager is not admin
     */
    public function assignManager(string $userUuid, string $managerUuid): User
    {
        return $this->transaction(function () use ($userUuid, $managerUuid) {
            $user = $this->findByUuidOrFail($userUuid);
            $manager = $this->findByUuidOrFail($managerUuid);

            // Verify manager has management authority
            if (!$manager->role->isAdmin()) {
                throw new BusinessException('المستخدم المحدد ليس مديراً', 400);
            }

            // Check not assigning user as their own manager
            if ($user->id === $manager->id) {
                throw new BusinessException('لا يمكن تعيين المستخدم كمدير لنفسه', 400);
            }

            $oldManagerId = $user->manager_id;

            $user->update(['manager_id' => $manager->id]);

            // Log assignment change
            $user->logAudit('manager_assigned', [
                'new_manager_id' => $manager->id,
                'old_manager_id' => $oldManagerId,
            ]);

            return $user->fresh();
        });
    }

    /**
     * Remove manager assignment from user
     *
     * @param string $userUuid User UUID
     * @return \App\Models\User Updated user instance
     */
    public function removeManager(string $userUuid): User
    {
        return $this->transaction(function () use ($userUuid) {
            $user = $this->findByUuidOrFail($userUuid);

            $oldManagerId = $user->manager_id;

            $user->update(['manager_id' => null]);

            // Log removal
            $user->logAudit('manager_removed', [
                'old_manager_id' => $oldManagerId,
            ]);

            return $user->fresh();
        });
    }

    /**
     * Get manager's subordinates
     *
     * @param string $managerUuid Manager UUID
     * @param array $filters Additional filters [status, role, search]
     * @return \Illuminate\Database\Eloquent\Collection
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
