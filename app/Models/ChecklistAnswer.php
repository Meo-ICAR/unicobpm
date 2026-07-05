<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistAnswer extends Model
{
    protected $table = 'checklist_answers';

    protected $fillable = [
        'process_instance_id',
        'checklist_item_id',
        'value_boolean',
        'value_text',
    ];

    protected $casts = [
        'value_boolean' => 'boolean',
    ];

    // =========================================================================
    // RELAZIONI
    // =========================================================================

    /**
     * La pratica in esecuzione a cui appartiene questa risposta.
     */
    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id');
    }

    /**
     * La definizione del campo/item a cui si sta rispondendo.
     */
    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
