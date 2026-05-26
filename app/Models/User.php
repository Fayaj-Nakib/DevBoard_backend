<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'digest_enabled', 'digest_hour', 'last_digest_sent',
        'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'digest_enabled'    => 'boolean',
        'digest_hour'       => 'integer',
        'last_digest_sent'  => 'date',
    ];

    public function workspaces()
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function memberships()
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function assignedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_assignees');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function watchedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_watchers');
    }

    public function timeLogs()
    {
        return $this->hasMany(TaskTimeLog::class);
    }
}
