<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'type',
        'source',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_2',
        'national_id',
        'passport_number',
        'date_of_birth',
        'gender',
        'nationality',
        'address',
        'city',
        'district',
        'neighborhood',
        'postal_code',
        'company_name',
        'job_title',
        'industry',
        'annual_income',
        'status',
        'priority',
        'budget_range',
        'preferred_property_types',
        'preferred_locations',
        'preferred_amenities',
        'min_bedrooms',
        'max_bedrooms',
        'min_area',
        'max_area',
        'min_budget',
        'max_budget',
        'financing_type',
        'down_payment',
        'bank_name',
        'has_mortgage_pre_approval',
        'mortgage_pre_approval_number',
        'assigned_agent_id',
        'created_by',
        'allow_sms',
        'allow_email',
        'allow_whatsapp',
        'communication_preferences',
        'converted_to_client_at',
        'last_contacted_at',
        'next_follow_up_at',
        'notes',
        'tags',
    ];

    protected $casts = [
        'preferred_property_types' => 'array',
        'preferred_locations' => 'array',
        'preferred_amenities' => 'array',
        'tags' => 'array',
        'communication_preferences' => 'array',
        'date_of_birth' => 'date',
        'annual_income' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'min_budget' => 'decimal:2',
        'max_budget' => 'decimal:2',
        'min_area' => 'decimal:2',
        'max_area' => 'decimal:2',
        'converted_to_client_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'has_mortgage_pre_approval' => 'boolean',
        'allow_sms' => 'boolean',
        'allow_email' => 'boolean',
        'allow_whatsapp' => 'boolean',
    ];

    protected $appends = [
        'full_name',
        'is_lead',
        'is_client',
        'days_since_last_contact',
    ];

    // ==================== RELATIONSHIPS ====================

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== SCOPES ====================

    public function scopeLeads(Builder $query)
    {
        return $query->where('status', 'lead');
    }

    public function scopeClients(Builder $query)
    {
        return $query->where('status', 'client');
    }

    public function scopeByType(Builder $query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCity(Builder $query, $city)
    {
        return $query->where('city', $city);
    }

    public function scopeByAgent(Builder $query, $agentId)
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    public function scopeSearch(Builder $query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'ILIKE', "%{$search}%")
              ->orWhere('last_name', 'ILIKE', "%{$search}%")
              ->orWhere('email', 'ILIKE', "%{$search}%")
              ->orWhere('phone', 'ILIKE', "%{$search}%")
              ->orWhere('code', 'ILIKE', "%{$search}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$search}%"]);
        });
    }

    // ==================== ACCESSORS ====================

    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getIsLeadAttribute()
    {
        return $this->status === 'lead';
    }

    public function getIsClientAttribute()
    {
        return $this->status === 'client';
    }

    public function getDaysSinceLastContactAttribute()
    {
        if (!$this->last_contacted_at) {
            return null;
        }

        return now()->diffInDays($this->last_contacted_at);
    }

    // ==================== BUSINESS METHODS ====================

    public function convertToClient()
    {
        if ($this->status !== 'client') {
            $this->update([
                'status' => 'client',
                'converted_to_client_at' => now(),
            ]);
        }
    }

    public function updateLastContact()
    {
        $this->update(['last_contacted_at' => now()]);
    }

    public function scheduleFollowUp($date)
    {
        $this->update(['next_follow_up_at' => $date]);
    }

    public function isOverdueForFollowUp()
    {
        if (!$this->next_follow_up_at) {
            return false;
        }

        return now()->greaterThan($this->next_follow_up_at);
    }
}
