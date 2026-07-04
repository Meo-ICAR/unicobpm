<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTaskItem extends Model
{
    protected $fillable = [
        'process_task_id',
        'name',
        'ordine',
        'action_type',
        'is_required',
        'document_type_id',
        'handler_job',
    ];

    protected function casts(): array
    {
        return [
            'ordine' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'process_task_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
