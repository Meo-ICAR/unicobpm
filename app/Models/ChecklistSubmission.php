<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistSubmission extends Model
{
    protected $fillable = [
        'checklist_id',
        'process_task_item_answer_id',
        'status',
        'submitted_at',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    /**
     * La risposta all'azione "Compilazione Checklist" del task a cui appartiene questa
     * compilazione (ProcessInstance -> ProcessTaskExecution -> ProcessTaskItemAnswer -> qui).
     */
    public function taskItemAnswer(): BelongsTo
    {
        return $this->belongsTo(ProcessTaskItemAnswer::class, 'process_task_item_answer_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ChecklistAnswer::class);
    }
}
