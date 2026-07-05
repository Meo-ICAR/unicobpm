<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $orderBy = 'ordine';

    //  protected $orderDirection = 'asc';
    protected $fillable = [
        'checklist_id',
        'item_code',
        'ordine',
        'name',
        'label',
        'question',
        'type',
        'options',
        'is_required',
        'trigger_model',
        'trigger_field',
        'trigger_state',
        'trigger_value',
        'exclude_field',
        'exclude_state',
        'exclude_value',
        'is_timestamp_update',
        'is_knockout',
        'knockout_value',
        'depends_on_code',
        'depends_on_value',
    ];

    protected function casts(): array
    {
        return [
            'ordine' => 'integer',
            'is_required' => 'boolean',
            'is_timestamp_update' => 'boolean',
            'is_knockout' => 'boolean',
            'options' => 'array',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
