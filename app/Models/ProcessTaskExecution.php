<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProcessTaskExecution extends Model
{
    protected $fillable = [
        'process_instance_id',
        'process_task_id',
        'assignee_type',
        'assignee_id',
        'started_at',
        'claimed_at',
        'completed_at',
        'execution_status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class);
    }

    public function processTask(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class);
    }

    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }
}
