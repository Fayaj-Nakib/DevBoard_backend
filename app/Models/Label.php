<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    use HasUuid;

    protected $fillable = ['workspace_id', 'created_by', 'name', 'color'];

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
        return $this->belongsToMany(Task::class, 'label_task');
    }
}
