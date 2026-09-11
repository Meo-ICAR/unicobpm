<?php

namespace App\Observers;

use App\Models\ProcessInstance;
use App\Models\ProcessTaskExecution;

class ProcessInstanceObserver
{
    /**
     * Prima che la pratica venga salvata nel database:
     * Troviamo il primo task del processo e lo impostiamo come task corrente.
     */
    public function creating(ProcessInstance $instance): void
    {
        /**
         * PROCEDURA DI CREAZIONE ISTANZA (ANTE-SALVATAGGIO):
         * Intercetta la fase preliminare al salvataggio nel database della nuova istanza.
         * Nello specifico:
         * 1. Verifica se non è già stato definito un task corrente.
         * 2. Recupera il primo task associato al processo ordinato secondo la colonna 'ordine'.
         * 3. Assegna l'ID del primo task a 'current_task_id' e imposta lo stato della pratica su 'running'.
         */
        if (! $instance->current_task_id) {
            $firstTask = $instance->process->tasks()->orderBy('ordine')->first();

            if ($firstTask) {
                $instance->current_task_id = $firstTask->id;
                $instance->status = 'in_progress';
            }
        }
    }

    /**
     * Subito dopo che la pratica è stata creata nel database:
     * Scriviamo il primo log e apriamo l'esecuzione (la coda di lavoro).
     */
    public function created(ProcessInstance $instance): void
    {
        /**
         * PROCEDURA DI AVVIO ISTANZA (POST-SALVATAGGIO):
         * Reagisce alla avvenuta memorizzazione fisica dell'istanza del workflow.
         * Nello specifico:
         * 1. Registra l'evento di avvio nell'activity log (alizharb/filament-activity-log).
         * 2. Se è presente un task corrente ('current_task_id'), genera il primo record di esecuzione
         *    nella tabella ProcessTaskExecution con stato 'pending' per esporlo ai reparti di competenza.
         */
        // 1. Tracciamento Audit Log
        activity('bpm')
            ->causedBy(auth()->user())
            ->performedOn($instance)
            ->event('instance_started')
            ->log('Pratica avviata sulla versione '.$instance->process->version);

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
