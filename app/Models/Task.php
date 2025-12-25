<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasUuid;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Carbon\Carbon;

class Task extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'deal_id',
        'due_date',
        'start_date',
        'completed_at',
        'estimated_minutes',
        'actual_minutes',
        'location',
        'latitude',
        'longitude',
        'notes',
        'attachments',
        'is_recurring',
        'recurrence_type',
        'recurrence_interval',
        'recurrence_days',
        'recurrence_end_date',
        'parent_task_id',
        'reminder_sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => TaskType::class,
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'due_date' => 'datetime',
        'start_date' => 'datetime',
        'completed_at' => 'datetime',
        'attachments' => 'array',
        'recurrence_days' => 'array',
        'recurrence_end_date' => 'date',
        'is_recurring' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'estimated_minutes' => 'integer',
        'actual_minutes' => 'integer',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'is_overdue',
        'is_due_today',
        'days_until_due',
        'type_label',
        'status_label',
        'priority_label',
        'formatted_due_date',
        'duration_formatted',
    ];

    /**
     * RELATIONSHIPS
     */

    /**
     * Get the user assigned to this task.
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created this task.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the client associated with this task.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the property associated with this task.
     */
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the deal associated with this task.
     */
    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Get the parent task if this is a subtask.
     */
    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Get the subtasks of this task.
     */
    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * SCOPES
     */

    /**
     * Scope a query to only include tasks assigned to a specific user.
     */
    public function scopeAssignedToUser(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope a query to only include tasks created by a specific user.
     */
    public function scopeCreatedByUser(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope a query to only include tasks with a specific status.
     */
    public function scopeWithStatus(Builder $query, TaskStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include tasks with a specific priority.
     */
    public function scopeWithPriority(Builder $query, TaskPriority $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include overdue tasks.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', TaskStatus::COMPLETED)
            ->where('status', '!=', TaskStatus::CANCELLED);
    }

    /**
     * Scope a query to only include tasks due today.
     */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('due_date', Carbon::today())
            ->where('status', '!=', TaskStatus::COMPLETED)
            ->where('status', '!=', TaskStatus::CANCELLED);
    }

    /**
     * Scope a query to only include upcoming tasks.
     */
    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->whereBetween('due_date', [now(), now()->addDays($days)])
            ->where('status', '!=', TaskStatus::COMPLETED)
            ->where('status', '!=', TaskStatus::CANCELLED);
    }

    /**
     * Scope a query to only include high priority tasks.
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [
            TaskPriority::HIGH,
            TaskPriority::URGENT
        ]);
    }

    /**
     * Scope a query to only include tasks related to a client.
     */
    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope a query to only include tasks related to a property.
     */
    public function scopeForProperty(Builder $query, int $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Scope a query to only include recurring tasks.
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope a query to search tasks by title or description.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'ILIKE', "%{$search}%")
              ->orWhere('description', 'ILIKE', "%{$search}%");
        });
    }

    /**
     * ACCESSORS
     */

    /**
     * Check if the task is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date || $this->status === TaskStatus::COMPLETED || $this->status === TaskStatus::CANCELLED) {
            return false;
        }

        return $this->due_date->isPast();
    }

    /**
     * Check if the task is due today.
     */
    public function getIsDueTodayAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        return $this->due_date->isToday();
    }

    /**
     * Get the number of days until the task is due.
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Get the formatted type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return TaskType::tryFrom($this->type)?->label() ?? $this->type;
    }

    /**
     * Get the formatted status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return TaskStatus::tryFrom($this->status)?->label() ?? $this->status;
    }

    /**
     * Get the formatted priority label.
     */
    public function getPriorityLabelAttribute(): string
    {
        return TaskPriority::tryFrom($this->priority)?->label() ?? $this->priority;
    }

    /**
     * Get the formatted due date.
     */
    public function getFormattedDueDateAttribute(): ?string
    {
        if (!$this->due_date) {
            return null;
        }

        return $this->due_date->format('Y-m-d H:i');
    }

    /**
     * Get the formatted duration.
     */
    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->estimated_minutes) {
            return null;
        }

        if ($this->estimated_minutes < 60) {
            return $this->estimated_minutes . ' دقيقة';
        }

        $hours = floor($this->estimated_minutes / 60);
        $minutes = $this->estimated_minutes % 60;

        if ($minutes === 0) {
            return $hours . ' ساعة';
        }

        return $hours . ' ساعة و ' . $minutes . ' دقيقة';
    }

    /**
     * Get the completion percentage if subtasks exist.
     */
    public function getCompletionPercentageAttribute(): float
    {
        $subtasks = $this->subtasks;
        
        if ($subtasks->isEmpty()) {
            return $this->status === TaskStatus::COMPLETED ? 100 : 0;
        }

        $completed = $subtasks->where('status', TaskStatus::COMPLETED)->count();
        
        return ($completed / $subtasks->count()) * 100;
    }

    /**
     * BUSINESS METHODS
     */

    /**
     * Mark the task as completed.
     *
     * @param string|null $notes Completion notes
     * @param int|null $actualMinutes Actual minutes spent
     * @return bool
     */
    public function markAsCompleted(?string $notes = null, ?int $actualMinutes = null): bool
    {
        $this->update([
            'status' => TaskStatus::COMPLETED,
            'completed_at' => now(),
            'notes' => $notes ? ($this->notes ? $this->notes . "\n\n" . $notes : $notes) : $this->notes,
            'actual_minutes' => $actualMinutes ?? $this->actual_minutes,
        ]);

        // If this is a recurring task, create the next occurrence
        if ($this->is_recurring && $this->shouldCreateNextOccurrence()) {
            $this->createNextOccurrence();
        }

        return true;
    }

    /**
     * Mark the task as in progress.
     *
     * @return bool
     */
    public function markAsInProgress(): bool
    {
        $this->update([
            'status' => TaskStatus::IN_PROGRESS,
            'start_date' => $this->start_date ?: now(),
        ]);

        return true;
    }

    /**
     * Reassign the task to another user.
     *
     * @param int $newUserId New user ID
     * @param string|null $reason Reason for reassignment
     * @return bool
     */
    public function reassignTo(int $newUserId, ?string $reason = null): bool
    {
        $oldUserId = $this->assigned_to;

        $this->update([
            'assigned_to' => $newUserId,
            'reassigned_at' => now(),
            'reassignment_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Update the task's due date.
     *
     * @param \Carbon\Carbon $newDueDate New due date
     * @param string|null $reason Reason for rescheduling
     * @return bool
     */
    public function reschedule(Carbon $newDueDate, ?string $reason = null): bool
    {
        $oldDueDate = $this->due_date;

        $this->update([
            'due_date' => $newDueDate,
            'rescheduled_at' => now(),
            'rescheduling_reason' => $reason,
        ]);

        return true;
    }

    /**
     * Add an attachment to the task.
     *
     * @param string $attachmentUrl URL of the attachment
     * @return void
     */
    public function addAttachment(string $attachmentUrl): void
    {
        $attachments = $this->attachments ?? [];
        $attachments[] = $attachmentUrl;
        
        $this->update(['attachments' => $attachments]);
    }

    /**
     * Check if task needs a reminder.
     *
     * @return bool
     */
    public function needsReminder(): bool
    {
        if (!$this->due_date || $this->status === TaskStatus::COMPLETED || $this->status === TaskStatus::CANCELLED) {
            return false;
        }

        // Send reminder 24 hours before due date
        $reminderTime = $this->due_date->subHours(24);
        
        return now()->greaterThanOrEqualTo($reminderTime) && 
               (!$this->reminder_sent_at || $this->reminder_sent_at->lessThan($reminderTime));
    }

    /**
     * Mark reminder as sent.
     *
     * @return void
     */
    public function markReminderAsSent(): void
    {
        $this->update(['reminder_sent_at' => now()]);
    }

    /**
     * Check if a recurring task should create next occurrence.
     *
     * @return bool
     */
    private function shouldCreateNextOccurrence(): bool
    {
        if (!$this->is_recurring || !$this->recurrence_type) {
            return false;
        }

        // Check if recurrence has ended
        if ($this->recurrence_end_date && now()->greaterThan($this->recurrence_end_date)) {
            return false;
        }

        // Check recurrence count if set
        if ($this->recurrence_count) {
            $occurrences = self::where('parent_task_id', $this->parent_task_id ?: $this->id)->count();
            return $occurrences < $this->recurrence_count;
        }

        return true;
    }

    /**
     * Create the next occurrence of a recurring task.
     *
     * @return \App\Models\Task|null
     */
    private function createNextOccurrence(): ?Task
    {
        $nextDueDate = $this->calculateNextDueDate();
        
        if (!$nextDueDate) {
            return null;
        }

        return self::create([
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'status' => TaskStatus::PENDING,
            'priority' => $this->priority,
            'assigned_to' => $this->assigned_to,
            'created_by' => $this->created_by,
            'client_id' => $this->client_id,
            'property_id' => $this->property_id,
            'deal_id' => $this->deal_id,
            'due_date' => $nextDueDate,
            'estimated_minutes' => $this->estimated_minutes,
            'is_recurring' => $this->is_recurring,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_days' => $this->recurrence_days,
            'recurrence_end_date' => $this->recurrence_end_date,
            'parent_task_id' => $this->parent_task_id ?: $this->id,
        ]);
    }

    /**
     * Calculate the next due date for a recurring task.
     *
     * @return \Carbon\Carbon|null
     */
    private function calculateNextDueDate(): ?Carbon
    {
        if (!$this->due_date) {
            return null;
        }

        $nextDate = $this->due_date->copy();
        
        switch ($this->recurrence_type) {
            case 'daily':
                $nextDate->addDays($this->recurrence_interval ?? 1);
                break;
                
            case 'weekly':
                $nextDate->addWeeks($this->recurrence_interval ?? 1);
                
                // Handle specific days of week
                if ($this->recurrence_days) {
                    $currentDay = $nextDate->dayOfWeek;
                    $nextDay = $this->findNextRecurrenceDay($currentDay);
                    
                    if ($nextDay !== null) {
                        $daysToAdd = $nextDay > $currentDay 
                            ? $nextDay - $currentDay 
                            : 7 - $currentDay + $nextDay;
                        
                        $nextDate->addDays($daysToAdd);
                    }
                }
                break;
                
            case 'monthly':
                $nextDate->addMonths($this->recurrence_interval ?? 1);
                break;
                
            case 'yearly':
                $nextDate->addYears($this->recurrence_interval ?? 1);
                break;
        }

        return $nextDate;
    }

    /**
     * Find the next recurrence day for weekly recurrence.
     *
     * @param int $currentDay Current day of week (0-6)
     * @return int|null
     */
    private function findNextRecurrenceDay(int $currentDay): ?int
    {
        if (!$this->recurrence_days) {
            return null;
        }

        // Sort days
        sort($this->recurrence_days);
        
        // Find next day
        foreach ($this->recurrence_days as $day) {
            if ($day > $currentDay) {
                return $day;
            }
        }
        
        // If no day found, return first day of next week
        return $this->recurrence_days[0];
    }
}
