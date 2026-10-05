<?php

namespace App\Models;

use App\Observers\ChecklistAnswerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ChecklistAnswerObserver::class)]
class ChecklistAnswer extends Model
{
    protected $table = 'checklist_answers';

    protected $fillable = [
        'process_instance_id',
        'checklist_submission_id',
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

    /**
     * La compilazione della checklist a cui appartiene questa risposta
     * (ProcessTaskItemAnswer -> ChecklistSubmission -> qui).
     */
    public function checklistSubmission(): BelongsTo
    {
        return $this->belongsTo(ChecklistSubmission::class);
    }
}
