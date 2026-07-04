<?php

namespace App\Observers;

use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItemAnswer;

class ProcessTaskItemAnswerObserver
{
    public function saved(ProcessTaskItemAnswer $answer): void
    {
        // 1. Recuperiamo la pratica in esecuzione
        $pratica = ProcessInstance::find($answer->process_instance_id);

        // Se la pratica è già chiusa o respinta, ci fermiamo
        if (! $pratica || in_array($pratica->status, ['completed', 'rejected', 'cancelled'])) {
            return;
        }

        $currentTaskId = $pratica->current_task_id;

        // Se non c'è un task corrente impostato, c'è un'anomalia, ci fermiamo
        if (! $currentTaskId) {
            return;
        }

        // 2. Troviamo tutte le azioni OBBLIGATORIE per il task corrente
        $requiredItemsCount = \DB::table('process_task_items')
            ->where('process_task_id', $currentTaskId)
            ->where('is_required', true)
            ->count();

        // 3. Contiamo quante di queste azioni obbligatorie hanno già una risposta
        $answeredItemsCount = \DB::table('process_task_item_answers')
            ->join('process_task_items', 'process_task_items.id', '=', 'process_task_item_answers.process_task_item_id')
            ->where('process_task_item_answers.process_instance_id', $pratica->id)
            ->where('process_task_items.process_task_id', $currentTaskId)
            ->where('process_task_items.is_required', true)
            ->count();

        // 4. Se abbiamo risposto a tutto, AVANZIAMO!
        if ($answeredItemsCount >= $requiredItemsCount) {
            $this->advanceToNextTask($pratica, $currentTaskId);
        }
    }

    /**
     * Metodo helper per trovare il prossimo task e far avanzare la pratica
     */
    private function advanceToNextTask(ProcessInstance $pratica, int $currentTaskId): void
    {
        // Troviamo l'ordine del task attuale
        $currentTask = ProcessTask::find($currentTaskId);

        // Recuperiamo tutti i task successivi del processo, in ordine
        $futureTasks = ProcessTask::where('process_id', $pratica->process_id)
            ->where('ordine', '>', $currentTask->ordine)
            ->orderBy('ordine', 'asc')
            ->get();

        $nextValidTask = null;

        // Iteriamo sui futuri task per trovare il primo APPLICABILE a questo agente
        foreach ($futureTasks as $task) {
            // isRequiredFor() è il metodo che avevamo creato per il conditional branching!
            if ($task->isRequiredFor($pratica->subject)) {
                $nextValidTask = $task;
                break;
            }
        }

        if ($nextValidTask) {
            // Passiamo al prossimo task
            $pratica->current_task_id = $nextValidTask->id;
            $pratica->status = 'in_progress';
            // Qui potresti anche far partire un evento/email per avvisare l'ufficio competente (RACI)
        } else {
            // Non ci sono più task da eseguire: PRATICA COMPLETATA!
            $pratica->current_task_id = null;
            $pratica->status = 'completed';
            $pratica->completed_at = now();

            // Opzionale: aggiornare lo stato dell'Agente a "attivo" in via definitiva
        }

        $pratica->save();
    }
}
