<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProcessTaskExecution extends Model
{
    protected $table = 'process_task_executions';

    protected $fillable = [
        'process_instance_id',
        'process_task_id',
        'assignee_type',
        'assignee_id',
        'started_at',
        'claimed_at',
        'completed_at',
        'escalation_level',
        'due_at',
        'execution_status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_at' => 'datetime',
        'escalation_level' => 'integer',
    ];

    // =========================================================================
    // RELAZIONI
    // =========================================================================

    /**
     * La pratica macro a cui appartiene questa esecuzione.
     */
    public function processInstance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id');
    }

    /**
     * La definizione del Task (il template con la configurazione RACI).
     */
    public function processTask(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'process_task_id');
    }

    /**
     * L'operatore (Employee o Consultant) che ha preso in carico l'esecuzione.
     */
    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // METODI UTILI E KPI (PER FILAMENT)
    // =========================================================================

    /**
     * Verifica se il task è attualmente scaduto rispetto allo SLA configurato.
     */
    public function isOverdue(): bool
    {
        if ($this->completed_at) {
            return $this->completed_at->gt($this->due_at);
        }

        return $this->due_at && now()->gt($this->due_at);
    }

    /**
     * Calcola il tempo di attesa in coda prima che un operatore lo prendesse in carico (in secondi).
     */
    public function QueueDurationInSeconds(): int
    {
        $end = $this->claimed_at ?? now();

        return $this->started_at->diffInSeconds($end);
    }

    /**
     * Calcola il tempo effettivo di lavorazione dell'operatore (in secondi).
     */
    public function processingDurationInSeconds(): ?int
    {
        if (! $this->claimed_at) {
            return null;
        }

        $end = $this->completed_at ?? now();

        return $this->claimed_at->diffInSeconds($end);
    }
}
