<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GitHubIntegration extends Model
{
    use HasUuid;

    protected $fillable = ['workspace_id', 'access_token', 'github_username', 'webhook_secret'];

    protected $hidden = ['access_token'];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function getDecryptedTokenAttribute(): string
    {
        return Crypt::decryptString($this->access_token);
    }

    public function setAccessTokenAttribute(string $value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }
}
