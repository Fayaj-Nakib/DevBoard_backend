<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Workspace extends Model
{
    use HasFactory, HasUuid, HasSlug;

    protected $fillable = ['name', 'slug', 'owner_id', 'require_2fa'];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function labels()
    {
        return $this->hasMany(Label::class);
    }

    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    public function userRole(User $user): ?string
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->value('role');
    }
}
