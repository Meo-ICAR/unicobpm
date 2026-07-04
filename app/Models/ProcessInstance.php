<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProcessInstance extends Model
{
    protected $fillable = [
        'process_id',
        'current_task_id',
        'subject_type',
        'subject_id',
        'current_assignee_type',
        'current_assignee_id',
        'status',
        'last_reminder_sent_at',
        'reminders_sent_count',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reminder_sent_at' => 'datetime',
            'reminders_sent_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function currentTask(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'current_task_id');
    }

    // Il soggetto della pratica (es: l'Agente)
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id', 'id');
    }

    // Chi ha preso in carico la coda di lavoro (Employer o Consultant)
    public function currentAssignee(): MorphTo
    {
        return $this->morphTo('current_assignee', 'current_assignee_type', 'current_assignee_id', 'id');
    }

    public function taskItemAnswers(): HasMany
    {
        return $this->hasMany(ProcessTaskItemAnswer::class);
    }

    public function checklistAnswers(): HasMany
    {
        return $this->hasMany(ChecklistAnswer::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProcessInstanceLog::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ProcessTaskExecution::class);
    }
}
