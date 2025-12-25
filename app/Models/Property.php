<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasUuid;
use App\Enums\PropertyType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyPurpose;

class Property extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $fillable = [
        'uuid',
        'code',
        'title',
        'description',
        'type',
        'purpose',
        'status',
        'address',
        'city',
        'district',
        'neighborhood',
        'google_maps_url',
        'latitude',
        'longitude',
        'bedrooms',
        'bathrooms',
        'living_rooms',
        'kitchens',
        'built_area',
        'land_area',
        'floor',
        'total_floors',
        'year_built',
        'furnishing',
        'price_egp',
        'price_usd',
        'price_type',
        'price_per_meter',
        'commission_amount',
        'commission_rate',
        'owner_id',
        'assigned_agent_id',
        'created_by',
        'images',
        'documents',
        'videos',
        'features',
        'amenities',
        'has_mortgage',
        'mortgage_details',
        'has_maintenance',
        'maintenance_fee',
        'property_tax_number',
        'deed_number',
        'is_featured',
        'is_verified',
        'verified_at',
        'view_count',
        'inquiry_count',
        'available_from',
        'available_to',
        'sold_at',
        'rented_at',
        'reserved_at',
    ];

    protected $casts = [
        'images' => 'array',
        'documents' => 'array',
        'videos' => 'array',
        'features' => 'array',
        'amenities' => 'array',
        'price_egp' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'price_per_meter' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'built_area' => 'decimal:2',
        'land_area' => 'decimal:2',
        'maintenance_fee' => 'decimal:2',
        'has_mortgage' => 'boolean',
        'has_maintenance' => 'boolean',
        'is_featured' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'available_from' => 'datetime',
        'available_to' => 'datetime',
        'sold_at' => 'datetime',
        'rented_at' => 'datetime',
        'reserved_at' => 'datetime',
        'type' => PropertyType::class,
        'status' => PropertyStatus::class,
        'purpose' => PropertyPurpose::class,
    ];

    protected $appends = [
        'formatted_price',
        'is_available',
        'primary_image',
        'total_area',
        'type_label',
        'status_label',
        'purpose_label',
        'price_per_meter_calculated',
    ];

    // ==================== RELATIONSHIPS ====================

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== SCOPES ====================

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', PropertyStatus::AVAILABLE);
    }

    public function scopeForSale(Builder $query): Builder
    {
        return $query->where('purpose', PropertyPurpose::SALE)
            ->orWhere('purpose', PropertyPurpose::BOTH);
    }

    public function scopeForRent(Builder $query): Builder
    {
        return $query->where('purpose', PropertyPurpose::RENT)
            ->orWhere('purpose', PropertyPurpose::BOTH);
    }

    public function scopeByType(Builder $query, PropertyType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeByAgent(Builder $query, int $agentId): Builder
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    public function scopeByOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'ILIKE', "%{$search}%")
              ->orWhere('code', 'ILIKE', "%{$search}%")
              ->orWhere('description', 'ILIKE', "%{$search}%")
              ->orWhere('address', 'ILIKE', "%{$search}%")
              ->orWhere('city', 'ILIKE', "%{$search}%")
              ->orWhere('district', 'ILIKE', "%{$search}%");
        });
    }

    public function scopeByBudget(Builder $query, float $min, float $max): Builder
    {
        return $query->whereBetween('price_egp', [$min, $max]);
    }

    public function scopeByBedrooms(Builder $query, int $min, int $max = null): Builder
    {
        if ($max) {
            return $query->whereBetween('bedrooms', [$min, $max]);
        }
        return $query->where('bedrooms', '>=', $min);
    }

    public function scopeByArea(Builder $query, float $min, float $max = null): Builder
    {
        if ($max) {
            return $query->whereBetween('built_area', [$min, $max]);
        }
        return $query->where('built_area', '>=', $min);
    }

    // ==================== ACCESSORS ====================

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_egp, 0) . ' ج.م';
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->status === PropertyStatus::AVAILABLE;
    }

    public function getPrimaryImageAttribute(): ?string
    {
        $images = $this->images ?? [];
        return $images[0] ?? null;
    }

    public function getTotalAreaAttribute(): ?float
    {
        if ($this->built_area && $this->land_area) {
            return $this->built_area + $this->land_area;
        }
        return $this->built_area ?? $this->land_area;
    }

    public function getTypeLabelAttribute(): string
    {
        return PropertyType::tryFrom($this->type)?->label() ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return PropertyStatus::tryFrom($this->status)?->label() ?? $this->status;
    }

    public function getPurposeLabelAttribute(): string
    {
        return PropertyPurpose::tryFrom($this->purpose)?->label() ?? $this->purpose;
    }

    public function getPricePerMeterCalculatedAttribute(): ?float
    {
        if ($this->price_per_meter) {
            return $this->price_per_meter;
        }

        if ($this->built_area && $this->built_area > 0) {
            return $this->price_egp / $this->built_area;
        }

        return null;
    }

    public function getFormattedPricePerMeterAttribute(): ?string
    {
        $price = $this->price_per_meter_calculated;
        return $price ? number_format($price, 2) . ' ج.م/م²' : null;
    }

    // ==================== BUSINESS METHODS ====================

    public function changeStatus(PropertyStatus $status, ?string $reason = null): bool
    {
        $oldStatus = $this->status;
        
        $this->update([
            'status' => $status,
            'status_changed_at' => now(),
            'status_change_reason' => $reason,
        ]);

        if ($status === PropertyStatus::SOLD) {
            $this->update(['sold_at' => now()]);
        } elseif ($status === PropertyStatus::RENTED) {
            $this->update(['rented_at' => now()]);
        } elseif ($status === PropertyStatus::AVAILABLE) {
            $this->update([
                'sold_at' => null,
                'rented_at' => null,
                'reserved_at' => null,
            ]);
        }

        return true;
    }

    public function assignAgent(int $agentId, ?string $reason = null): bool
    {
        $oldAgentId = $this->assigned_agent_id;
        
        $this->update([
            'assigned_agent_id' => $agentId,
            'agent_assigned_at' => now(),
            'assignment_reason' => $reason,
        ]);

        return true;
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function incrementInquiryCount(): void
    {
        $this->increment('inquiry_count');
    }

    public function verify(int $verifiedBy, ?string $notes = null): bool
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'verification_notes' => $notes,
        ]);

        return true;
    }

    public function markAsFeatured(bool $featured = true, ?string $reason = null): bool
    {
        $this->update([
            'is_featured' => $featured,
            'featured_at' => $featured ? now() : null,
            'featured_reason' => $reason,
        ]);

        return true;
    }

    public function calculateCommission(): float
    {
        if ($this->commission_amount) {
            return $this->commission_amount;
        }

        return ($this->price_egp * $this->commission_rate) / 100;
    }

    public function isAvailableForSale(): bool
    {
        return $this->is_available && $this->purpose->isForSale();
    }

    public function isAvailableForRent(): bool
    {
        return $this->is_available && $this->purpose->isForRent();
    }

    public function addImage(string $imageUrl, bool $isPrimary = false): void
    {
        $images = $this->images ?? [];
        
        if ($isPrimary) {
            array_unshift($images, $imageUrl);
        } else {
            $images[] = $imageUrl;
        }
        
        $this->update(['images' => $images]);
    }
}
