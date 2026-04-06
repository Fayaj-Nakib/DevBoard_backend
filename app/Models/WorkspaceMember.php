<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model
{
    use HasUuid;

    protected $fillable = ['workspace_id', 'user_id', 'role'];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
