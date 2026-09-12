<?php

namespace App\Models;

use App\Observers\ProcessTaskExecutionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

#[ObservedBy(ProcessTaskExecutionObserver::class)]
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
        'mandatory_days_to_complete',
        'execution_status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_at' => 'datetime',
        'mandatory_days_to_complete' => 'integer',
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
     * L'operatore (Employee o Client) che ha preso in carico l'esecuzione.
     */
    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // METODI UTILI E KPI (PER FILAMENT)
    // =========================================================================

    /**
     * La scadenza effettiva di questo step: se è stato impostato un termine
     * tassativo in giorni per QUESTA esecuzione (facoltativo, indipendente dal
     * default ereditato dal ProcessTask template), prevale su `due_at`.
     */
    public function effectiveDueAt(): ?Carbon
    {
        if ($this->mandatory_days_to_complete !== null && $this->started_at) {
            return $this->started_at->copy()->addDays($this->mandatory_days_to_complete);
        }

        return $this->due_at;
    }

    /**
     * Verifica se il task è attualmente scaduto rispetto allo SLA configurato.
     */
    public function isOverdue(): bool
    {
        $dueAt = $this->effectiveDueAt();

        if ($this->completed_at) {
            return $dueAt && $this->completed_at->gt($dueAt);
        }

        return $dueAt && now()->gt($dueAt);
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
