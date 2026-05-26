<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasUuid;

    protected $fillable = ['project_id', 'user_id', 'status', 'format', 'tasks_created', 'error_message'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
