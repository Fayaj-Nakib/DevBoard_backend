<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasUuid;

    protected $fillable = [
        'workspace_id', 'name', 'url', 'secret', 'events', 'is_active',
    ];

    protected $casts = [
        'events'    => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['secret'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest();
    }
}
