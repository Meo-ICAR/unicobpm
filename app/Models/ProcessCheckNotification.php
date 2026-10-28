<?php

namespace App\Models;

use App\Enums\Severity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un avviso mandato da un controllo esterno: serve a non rimandarlo ogni giorno e a farlo salire di grado se il problema resta.
 */
class ProcessCheckNotification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
            'effective_severity' => Severity::class,
            'sent_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProcessTaskItem::class, 'process_task_item_id');
    }
}
