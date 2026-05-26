<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'project_id', 'parent_id', 'milestone_id', 'sprint_id', 'project_status_id', 'created_by',
        'title', 'description', 'status', 'priority', 'position',
        'due_date', 'started_at', 'estimate',
        'is_backlog', 'backlog_position',
        'recurrence_rule', 'recurrence_ends_at', 'recurrence_parent_id',
        'github_issue_number', 'github_pr_number', 'github_pr_state',
    ];

    protected $casts = [
        'due_date'            => 'date',
        'started_at'          => 'date',
        'estimate'            => 'integer',
        'is_backlog'          => 'boolean',
        'backlog_position'    => 'integer',
        'recurrence_ends_at'   => 'datetime',
        'github_issue_number'  => 'integer',
        'github_pr_number'     => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->select('users.id', 'users.name', 'users.email');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label_task');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')
            ->select('users.id', 'users.name', 'users.email');
    }

    public function blockedBy(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_id')
            ->withPivot('type');
    }

    public function blocking(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_id', 'task_id')
            ->withPivot('type');
    }

    public function projectStatus(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'recurrence_parent_id');
    }

    public function recurrenceInstances(): HasMany
    {
        return $this->hasMany(Task::class, 'recurrence_parent_id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class)->latest('started_at');
    }

    public function getTotalLoggedMinutesAttribute(): int
    {
        return (int) $this->timeLogs()
            ->whereNotNull('duration_minutes')
            ->sum('duration_minutes');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
