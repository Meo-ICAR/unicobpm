<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $fillable = [
        'checklist_id',
        'label',
        'type',
        'target_model',
        'target_field',
        'is_knockout',
    ];

    protected function casts(): array
    {
        return [
            'is_knockout' => 'boolean',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
