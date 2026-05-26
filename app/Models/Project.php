<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'workspace_id', 'created_by', 'name', 'description', 'status',
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
}
