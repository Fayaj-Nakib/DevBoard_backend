<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'project_id', 'parent_id', 'created_by',
        'title', 'description', 'status', 'priority', 'position', 'due_date',
    ];

    protected $casts = ['due_date' => 'date'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees')->select('users.id', 'users.name', 'users.email');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class, 'label_task');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
