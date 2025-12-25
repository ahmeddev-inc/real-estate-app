<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasUuid;
use App\Enums\ClientType;
use App\Enums\ClientStatus;
use App\Enums\Priority;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

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
        'type' => ClientType::class,
        'status' => ClientStatus::class,
        'priority' => Priority::class,
    ];

    protected $appends = [
        'full_name',
        'is_lead',
        'is_client',
        'days_since_last_contact',
        'type_label',
        'status_label',
        'priority_label',
        'is_overdue_for_follow_up',
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

    public function scopeLeads(Builder $query): Builder
    {
        return $query->where('status', ClientStatus::LEAD);
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->where('status', ClientStatus::CLIENT);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', ClientStatus::INACTIVE)
            ->where('status', '!=', ClientStatus::BLACKLISTED);
    }

    public function scopeByType(Builder $query, ClientType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }

    public function scopeByAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [Priority::HIGH, Priority::URGENT, Priority::VIP]);
    }

    public function scopeNeedsFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->whereNotIn('status', [ClientStatus::INACTIVE, ClientStatus::BLACKLISTED]);
    }

    public function scopeSearch(Builder $query, string $search): Builder
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

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getIsLeadAttribute(): bool
    {
        return $this->status === ClientStatus::LEAD;
    }

    public function getIsClientAttribute(): bool
    {
        return $this->status === ClientStatus::CLIENT;
    }

    public function getDaysSinceLastContactAttribute(): ?int
    {
        if (!$this->last_contacted_at) {
            return null;
        }

        return now()->diffInDays($this->last_contacted_at);
    }

    public function getTypeLabelAttribute(): string
    {
        return ClientType::tryFrom($this->type)?->label() ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return ClientStatus::tryFrom($this->status)?->label() ?? $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        return Priority::tryFrom($this->priority)?->label() ?? $this->priority;
    }

    public function getIsOverdueForFollowUpAttribute(): bool
    {
        if (!$this->next_follow_up_at) {
            return false;
        }

        return now()->greaterThan($this->next_follow_up_at);
    }

    public function getBudgetRangeAttribute(): string
    {
        if ($this->min_budget && $this->max_budget) {
            return number_format($this->min_budget, 0) . ' - ' . number_format($this->max_budget, 0) . ' ج.م';
        } elseif ($this->min_budget) {
            return 'من ' . number_format($this->min_budget, 0) . ' ج.م';
        } elseif ($this->max_budget) {
            return 'حتى ' . number_format($this->max_budget, 0) . ' ج.م';
        }
        
        return 'غير محدد';
    }

    // ==================== BUSINESS METHODS ====================

    public function convertToClient(): bool
    {
        if ($this->status !== ClientStatus::CLIENT) {
            $this->update([
                'status' => ClientStatus::CLIENT,
                'converted_to_client_at' => now(),
            ]);
            return true;
        }
        return false;
    }

    public function convertToProspect(): bool
    {
        if ($this->status === ClientStatus::LEAD) {
            $this->update(['status' => ClientStatus::PROSPECT]);
            return true;
        }
        return false;
    }

    public function updateLastContact(): void
    {
        $this->update(['last_contacted_at' => now()]);
    }

    public function scheduleFollowUp(\DateTimeInterface $date): void
    {
        $this->update(['next_follow_up_at' => $date]);
    }

    public function scheduleAutoFollowUp(): void
    {
        $followUpDays = $this->priority->getFollowUpDays();
        $nextDate = now()->addDays($followUpDays);
        $this->scheduleFollowUp($nextDate);
    }

    public function markAsContacted(): void
    {
        $this->updateLastContact();
        $this->scheduleAutoFollowUp();
    }

    public function addNote(string $note, int $userId): void
    {
        $notes = $this->notes ?? '';
        $timestamp = now()->format('Y-m-d H:i:s');
        $userNote = "[{$timestamp}] المستخدم #{$userId}: {$note}\n";
        
        $this->update(['notes' => $notes . $userNote]);
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $index = array_search($tag, $tags);
        if ($index !== false) {
            unset($tags[$index]);
            $this->update(['tags' => array_values($tags)]);
        }
    }

    public function matchesProperty(Property $property): bool
    {
        // مطابقة الميزانية
        if ($this->min_budget && $property->price_egp < $this->min_budget) {
            return false;
        }
        if ($this->max_budget && $property->price_egp > $this->max_budget) {
            return false;
        }

        // مطابقة عدد الغرف
        if ($this->min_bedrooms && $property->bedrooms < $this->min_bedrooms) {
            return false;
        }
        if ($this->max_bedrooms && $property->bedrooms > $this->max_bedrooms) {
            return false;
        }

        // مطابقة المساحة
        if ($this->min_area && $property->built_area < $this->min_area) {
            return false;
        }
        if ($this->max_area && $property->built_area > $this->max_area) {
            return false;
        }

        // مطابقة الموقع
        if (!empty($this->preferred_locations) && !in_array($property->city, $this->preferred_locations)) {
            return false;
        }

        // مطابقة نوع العقار
        if (!empty($this->preferred_property_types) && !in_array($property->type, $this->preferred_property_types)) {
            return false;
        }

        return true;
    }

    public function getMatchScore(Property $property): float
    {
        $score = 0;
        $totalCriteria = 0;

        // مطابقة الميزانية (40%)
        if ($this->min_budget || $this->max_budget) {
            $totalCriteria += 40;
            if ($this->matchesBudget($property->price_egp)) {
                $score += 40;
            }
        }

        // مطابقة الموقع (30%)
        if (!empty($this->preferred_locations)) {
            $totalCriteria += 30;
            if (in_array($property->city, $this->preferred_locations)) {
                $score += 30;
            }
        }

        // مطابقة عدد الغرف (20%)
        if ($this->min_bedrooms || $this->max_bedrooms) {
            $totalCriteria += 20;
            if ($this->matchesBedrooms($property->bedrooms)) {
                $score += 20;
            }
        }

        // مطابقة نوع العقار (10%)
        if (!empty($this->preferred_property_types)) {
            $totalCriteria += 10;
            if (in_array($property->type, $this->preferred_property_types)) {
                $score += 10;
            }
        }

        if ($totalCriteria === 0) {
            return 100; // إذا لم تكن هناك معايير، كل العقارات مناسبة
        }

        return ($score / $totalCriteria) * 100;
    }

    private function matchesBudget(float $price): bool
    {
        if ($this->min_budget && $price < $this->min_budget) {
            return false;
        }
        if ($this->max_budget && $price > $this->max_budget) {
            return false;
        }
        return true;
    }

    private function matchesBedrooms(int $bedrooms): bool
    {
        if ($this->min_bedrooms && $bedrooms < $this->min_bedrooms) {
            return false;
        }
        if ($this->max_bedrooms && $bedrooms > $this->max_bedrooms) {
            return false;
        }
        return true;
    }
}
