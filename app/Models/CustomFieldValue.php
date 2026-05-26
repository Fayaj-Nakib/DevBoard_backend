<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    use HasUuid;

    protected $fillable = ['field_definition_id', 'task_id', 'value'];

    public function definition()
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'field_definition_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
