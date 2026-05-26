<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    use HasUuid;

    protected $fillable = ['project_id', 'name', 'field_type', 'options', 'position', 'is_required'];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function values()
    {
        return $this->hasMany(CustomFieldValue::class, 'field_definition_id');
    }
}
