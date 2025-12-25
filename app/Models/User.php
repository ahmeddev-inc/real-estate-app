<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'phone',
        'national_id',
        'password',
        'role',
        'user_type',
        'avatar',
        'address',
        'city',
        'country',
        'status',
        'commission_rate',
        'employee_id',
        'job_title',
        'manager_id',
        'branch_id',
        'permissions',
        'settings',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'suspended_at',
        'suspension_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'suspended_at' => 'datetime',
        'permissions' => 'array',
        'settings' => 'array',
        'commission_rate' => 'decimal:2',
    ];

    protected $appends = [
        'full_name',
        'is_admin',
        'is_agent',
        'is_active',
    ];

    // ==================== RELATIONSHIPS ====================

    public function managedProperties()
    {
        return $this->hasMany(Property::class, 'assigned_agent_id');
    }

    public function ownedProperties()
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    public function assignedClients()
    {
        return $this->hasMany(Client::class, 'assigned_agent_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    // ==================== SCOPES ====================

    public function scopeAgents($query)
    {
        return $query->where('role', 'agent');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['super_admin', 'admin']);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'ILIKE', "%{$search}%")
              ->orWhere('last_name', 'ILIKE', "%{$search}%")
              ->orWhere('email', 'ILIKE', "%{$search}%")
              ->orWhere('phone', 'ILIKE', "%{$search}%")
              ->orWhere('employee_id', 'ILIKE', "%{$search}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
        });
    }

    // ==================== ACCESSORS ====================

    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getIsAdminAttribute()
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function getIsAgentAttribute()
    {
        return $this->role === 'agent';
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    public function getInitialsAttribute()
    {
        return strtoupper(
            substr($this->first_name, 0, 1) . 
            substr($this->last_name, 0, 1)
        );
    }

    // ==================== BUSINESS METHODS ====================

    public function hasPermission($permission)
    {
        if ($this->is_admin) {
            return true;
        }

        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    public function updateLastLogin($ip)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    public function suspend($reason)
    {
        $this->update([
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'suspended_at' => now(),
        ]);
    }

    public function activate()
    {
        $this->update([
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_at' => null,
        ]);
    }
}
