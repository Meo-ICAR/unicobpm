<?php

namespace App\Models;

use App\Observers\ProcessTaskItemAnswerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ProcessTaskItemAnswerObserver::class)]
class ProcessTaskItemAnswer extends Model
{
    protected $fillable = [
        'process_instance_id',
        'process_task_item_id',
        'document_id',
        'value_boolean',
        'value_text',
        'user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'value_boolean' => 'boolean',
            'user_id' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function processTaskItem(): BelongsTo
    {
        return $this->belongsTo(ProcessTaskItem::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
