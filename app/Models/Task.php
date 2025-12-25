<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUuid;
use App\Traits\HasAuditLog;
use App\Enums\TaskPriority;

class Task extends Model
{
    use HasFactory, SoftDeletes, HasUuid, HasAuditLog;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'client_id',
        'property_id',
        'due_date',
        'completed_at',
        'completion_notes',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'is_overdue',
        'priority_label',
        'priority_color',
        'type_label',
    ];

    // العلاقات
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // النطاقات
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereIn('status', ['pending', 'in_progress']);
    }

    public function scopeByAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today());
    }

    // الخصائص المحسوبة
    public function getIsOverdueAttribute()
    {
        return $this->due_date && $this->due_date->lt(now()) && 
               in_array($this->status, ['pending', 'in_progress']);
    }

    public function getPriorityLabelAttribute()
    {
        return TaskPriority::tryFrom($this->priority)?->label() ?? $this->priority;
    }

    public function getPriorityColorAttribute()
    {
        return TaskPriority::tryFrom($this->priority)?->color() ?? 'gray';
    }

    public function getTypeLabelAttribute()
    {
        return match($this->type) {
            'follow_up' => 'متابعة',
            'property_viewing' => 'معاينة عقار',
            'contract_signing' => 'توقيع عقد',
            'payment_collection' => 'تحصيل دفعة',
            'other' => 'أخرى',
            default => $this->type,
        };
    }

    public function getDaysLeftAttribute()
    {
        if (!$this->due_date) return null;
        
        return now()->diffInDays($this->due_date, false);
    }

    // طرق الأعمال
    public function markAsInProgress()
    {
        $this->update(['status' => 'in_progress']);
    }

    public function markAsCompleted($notes = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_notes' => $notes,
        ]);
    }

    public function reassignTo($userId)
    {
        $this->update(['assigned_to' => $userId]);
    }

    public function updateDueDate($date)
    {
        $this->update(['due_date' => $date]);
    }

    public function isAssignedTo($userId)
    {
        return $this->assigned_to == $userId;
    }

    public function canBeCompleted()
    {
        return in_array($this->status, ['pending', 'in_progress']);
    }
}
