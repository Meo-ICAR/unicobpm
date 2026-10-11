<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Risposta email collegata a un processo, con l'interpretazione e l'esito (avanzato / da rivedere / ignorata). */
class ProcessEmailReply extends Model
{
    public const APPLIED = 'applied';

    public const NEEDS_REVIEW = 'needs_review';

    public const IGNORED = 'ignored';

    public const NO_ACTION = 'no_action';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['proposed_date' => 'date', 'decided_at' => 'datetime'];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcessTaskItem::class, 'process_task_item_id');
    }
}
