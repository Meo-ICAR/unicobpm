<?php

namespace App\Observers;

use App\Models\ProcessInstance;
use App\Models\ProcessInstanceLog;
use App\Models\ProcessTaskExecution;

class ProcessInstanceObserver
{
    /**
     * Prima che la pratica venga salvata nel database:
     * Troviamo il primo task del processo e lo impostiamo come task corrente.
     */
    public function creating(ProcessInstance $instance): void
    {
        if (! $instance->current_task_id) {
            $firstTask = $instance->process->tasks()->orderBy('ordine')->first();

            if ($firstTask) {
                $instance->current_task_id = $firstTask->id;
                $instance->status = 'running';
            }
        }
    }

    /**
     * Subito dopo che la pratica è stata creata nel database:
     * Scriviamo il primo log e apriamo l'esecuzione (la coda di lavoro).
     */
    public function created(ProcessInstance $instance): void
    {
        // 1. Tracciamento Audit Log
        ProcessInstanceLog::create([
            'process_instance_id' => $instance->id,
            'user_id' => auth()->id() ?? 0, // 0 = System
            'event' => 'instance_started',
            'payload' => [
                'message' => 'Pratica avviata sulla versione '.$instance->process->version,
            ],
        ]);

        // 2. Creazione della coda di esecuzione per il primo Task
        if ($instance->current_task_id) {
            ProcessTaskExecution::create([
                'process_instance_id' => $instance->id,
                'process_task_id' => $instance->current_task_id,
                'started_at' => now(),
                'execution_status' => 'pending', // In attesa che un dipartimento faccia il "Claim"
            ]);
        }
    }
}
