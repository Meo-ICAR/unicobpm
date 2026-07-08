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
        /**
         * PROCEDURA DI AVVIO ISTANZA (POST-SALVATAGGIO):
         * Reagisce alla avvenuta memorizzazione fisica dell'istanza del workflow.
         * Nello specifico:
         * 1. Inserisce una riga nella tabella ProcessInstanceLog come audit log iniziale dell'avvio della pratica.
         * 2. Se è presente un task corrente ('current_task_id'), genera il primo record di esecuzione
         *    nella tabella ProcessTaskExecution con stato 'pending' per esporlo ai reparti di competenza.
         */
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
