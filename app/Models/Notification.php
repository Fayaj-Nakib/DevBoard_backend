<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;

    use HasUuid;

    protected $fillable = ['user_id', 'type', 'data', 'read_at', 'created_at'];

    protected $casts = [
        'data'       => 'array',
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
