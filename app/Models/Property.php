<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Property extends Model
{
    use HasFactory, SoftDeletes;

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
    ];

    protected $appends = [
        'formatted_price',
        'is_available',
        'primary_image',
        'total_area',
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

    public function scopeAvailable(Builder $query)
    {
        return $query->where('status', 'available');
    }

    public function scopeForSale(Builder $query)
    {
        return $query->where('purpose', 'sale')->orWhere('purpose', 'both');
    }

    public function scopeForRent(Builder $query)
    {
        return $query->where('purpose', 'rent')->orWhere('purpose', 'both');
    }

    public function scopeByType(Builder $query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCity(Builder $query, $city)
    {
        return $query->where('city', $city);
    }

    public function scopeFeatured(Builder $query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'ILIKE', "%{$search}%")
              ->orWhere('code', 'ILIKE', "%{$search}%")
              ->orWhere('description', 'ILIKE', "%{$search}%")
              ->orWhere('address', 'ILIKE', "%{$search}%");
        });
    }

    // ==================== ACCESSORS ====================

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price_egp, 0) . ' ج.م';
    }

    public function getIsAvailableAttribute()
    {
        return $this->status === 'available';
    }

    public function getPrimaryImageAttribute()
    {
        $images = $this->images ?? [];
        return $images[0] ?? null;
    }

    public function getTotalAreaAttribute()
    {
        if ($this->built_area && $this->land_area) {
            return $this->built_area + $this->land_area;
        }
        return $this->built_area ?? $this->land_area;
    }

    // ==================== BUSINESS METHODS ====================

    public function changeStatus($status, $reason = null)
    {
        $oldStatus = $this->status;
        
        $this->update(['status' => $status]);

        if ($status === 'sold') {
            $this->update(['sold_at' => now()]);
        } elseif ($status === 'rented') {
            $this->update(['rented_at' => now()]);
        }

        return true;
    }

    public function incrementViewCount()
    {
        $this->increment('view_count');
    }

    public function incrementInquiryCount()
    {
        $this->increment('inquiry_count');
    }
}
