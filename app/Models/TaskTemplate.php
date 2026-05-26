<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplate extends Model
{
    use HasUuid;

    protected $fillable = [
        'project_id', 'created_by', 'name', 'default_title',
        'description', 'label_ids', 'estimate', 'checklist', 'priority',
    ];

    protected $casts = [
        'label_ids' => 'array',
        'checklist' => 'array',
        'estimate' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
