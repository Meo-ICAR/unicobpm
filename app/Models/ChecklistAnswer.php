<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistAnswer extends Model
{
    protected $fillable = [
        'process_instance_id',
        'checklist_item_id',
        'value_boolean',
        'value_text',
    ];

    protected function casts(): array
    {
        return [
            'value_boolean' => 'boolean',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }
}
