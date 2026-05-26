<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'workspace_id', 'created_by', 'name', 'description', 'status', 'github_repo',
    ];

    protected $casts = ['archived_at' => 'datetime'];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function sprints()
    {
        return $this->hasMany(Sprint::class)->orderBy('start_date');
    }

    public function statuses()
    {
        return $this->hasMany(ProjectStatus::class)->orderBy('position');
    }

    public function taskTemplates()
    {
        return $this->hasMany(TaskTemplate::class)->orderBy('name');
    }

    public function automationRules()
    {
        return $this->hasMany(AutomationRule::class);
    }

    public function projectMembers()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function customFieldDefinitions()
    {
        return $this->hasMany(CustomFieldDefinition::class)->orderBy('position');
    }

    public function importJobs()
    {
        return $this->hasMany(ImportJob::class);
    }

    /** Returns the project-level role for a user, or null if not explicitly set. */
    public function userRole(User $user): ?string
    {
        return $this->projectMembers()
            ->where('user_id', $user->id)
            ->value('role');
    }
}
