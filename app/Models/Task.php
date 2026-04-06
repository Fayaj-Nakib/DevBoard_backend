<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasUuid;

    protected $fillable = [
        'project_id', 'assignee_id', 'created_by',
        'title', 'description', 'status', 'priority', 'position', 'due_date',
    ];

    protected $casts = ['due_date' => 'date'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }
}
