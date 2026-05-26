<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasUuid;

    protected $fillable = [
        'project_id', 'name', 'is_active',
        'trigger_type', 'trigger_config',
        'action_type', 'action_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'trigger_config' => 'array',
        'action_config' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class, 'rule_id')->latest('triggered_at');
    }
}
