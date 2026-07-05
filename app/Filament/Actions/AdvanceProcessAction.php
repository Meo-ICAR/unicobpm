<?php

namespace App\Filament\Actions;

use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdvanceProcessAction
{
    /**
     * Avanza o retrocede una pratica nel workflow.
     *
     * @param  ProcessInstance  $instance  L'istanza della pratica
     * @param  Model  $user  L'operatore loggato che sta eseguendo l'azione
     * @param  string  $actionType  'complete' per andare avanti, 'reject' per tornare indietro
     */
    public function execute(ProcessInstance $instance, Model $user, string $actionType = 'complete'): ProcessInstance
    {
        return DB::transaction(function () use ($instance, $user, $actionType) {

            // 1. Recuperiamo l'esecuzione attiva per lo step corrente
            $currentExecution = $instance->currentTaskExecution;

            if ($currentExecution) {
                // Chiudiamo l'esecuzione attuale impostando l'operatore e il timestamp
                $currentExecution->update([
                    'assignee_type' => get_class($user),
                    'assignee_id' => $user->getKey(),
                    'completed_at' => now(),
                    'execution_status' => $actionType === 'complete' ? 'completed' : 'rejected_and_rewinded',
                ]);
            }

            // 2. Calcoliamo il prossimo step in base all'ordinamento del template del processo
            $currentTask = $instance->currentTask;
            $nextTask = null;

            if ($actionType === 'complete') {
                // Cerchiamo il primo task con un ordine superiore a quello attuale
                $nextTask = ProcessTask::where('process_id', $instance->process_id)
                    ->where('order', '>', $currentTask->order)
                    ->orderBy('order', 'asc')
                    ->first();
            } else {
                // REWIND: Cerchiamo il task immediatamente precedente
                $nextTask = ProcessTask::where('process_id', $instance->process_id)
                    ->where('order', '<', $currentTask->order)
                    ->orderBy('order', 'desc')
                    ->first();
            }

            // 3. Applichiamo i cambiamenti di stato all'istanza master della pratica
            if ($nextTask) {
                // C'è un altro step da fare (avanti o indietro)
                $instance->update([
                    'current_task_id' => $nextTask->id,
                    'current_assignee_type' => null, // Torna libero in coda per il nuovo reparto RACI
                    'current_assignee_id' => null,
                    'status' => 'in_progress',
                ]);

                // Generiamo la nuova riga di esecuzione in coda per il prossimo ufficio
                $dueAt = $nextTask->days_to_complete
                    ? now()->addDays($nextTask->days_to_complete)
                    : null;

                ProcessTaskExecution::create([
                    'process_instance_id' => $instance->id,
                    'process_task_id' => $nextTask->id,
                    'started_at' => now(),
                    'due_at' => $dueAt,
                    'execution_status' => 'in_progress',
                ]);

            } else {
                // Non ci sono più task successivi: Il processo è terminato con successo!
                $instance->update([
                    'current_task_id' => null,
                    'current_assignee_type' => null,
                    'current_assignee_id' => null,
                    'status' => $actionType === 'complete' ? 'completed' : 'rejected',
                    'completed_at' => now(),
                ]);
            }

            return $instance;
        });
    }
}
