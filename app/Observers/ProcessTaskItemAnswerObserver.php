<?php

namespace App\Observers;

use App\Models\ProcessInstanceLog;
use App\Models\ProcessTaskExecution;
use App\Models\ProcessTaskItemAnswer;

class ProcessTaskItemAnswerObserver
{
    /**
     * Quando viene salvata una risposta/azione (es. caricato un file, inviata un'email)
     */
    public function created(ProcessTaskItemAnswer $answer): void
    {
        $instance = $answer->processInstance;
        $currentTask = $instance->currentTask;
        $item = $answer->processTaskItem;

        // 1. Log dell'azione completata
        ProcessInstanceLog::create([
            'process_instance_id' => $instance->id,
            'user_id' => auth()->id() ?? 0,
            'event' => 'action_completed',
            'payload' => [
                'action_name' => $item->name,
                'action_type' => $item->action_type,
            ],
        ]);

        // 2. Controllo Avanzamento: Il Task è finito?
        $this->checkTaskCompletion($instance, $currentTask);
    }

    /**
     * Verifica se tutte le azioni obbligatorie del task corrente sono state completate.
     */
    protected function checkTaskCompletion($instance, $currentTask): void
    {
        // Quante azioni sono obbligatorie in questo task?
        $requiredItemsCount = $currentTask->items()->where('is_required', true)->count();

        // Quante risposte abbiamo nel DB per le azioni obbligatorie di QUESTO task?
        $completedRequiredItemsCount = $instance->taskItemAnswers()
            ->whereHas('processTaskItem', function ($query) use ($currentTask) {
                $query->where('process_task_id', $currentTask->id)
                    ->where('is_required', true);
            })->count();

        // Se abbiamo risposto a tutte le azioni obbligatorie, il task è concluso!
        if ($completedRequiredItemsCount >= $requiredItemsCount) {
            $this->advanceToNextTask($instance, $currentTask);
        }
    }

    /**
     * Chiude il task attuale e sposta la pratica a quello successivo.
     */
    protected function advanceToNextTask($instance, $currentTask): void
    {
        // 1. Chiudiamo l'esecuzione attuale
        $currentExecution = ProcessTaskExecution::where('process_instance_id', $instance->id)
            ->where('process_task_id', $currentTask->id)
            ->whereNull('completed_at')
            ->first();

        if ($currentExecution) {
            $currentExecution->update([
                'completed_at' => now(),
                'execution_status' => 'completed',
            ]);
        }

        // 2. Troviamo il prossimo task
        $nextTask = $instance->process->tasks()
            ->where('ordine', '>', $currentTask->ordine)
            ->orderBy('ordine')
            ->first();

        if ($nextTask) {
            // Avanziamo al prossimo Task
            $instance->update([
                'current_task_id' => $nextTask->id,
                'current_assignee_type' => null, // Resettiamo l'assegnatario, andrà riclaimato dal nuovo reparto!
                'current_assignee_id' => null,
            ]);

            // Creiamo la nuova coda di esecuzione
            ProcessTaskExecution::create([
                'process_instance_id' => $instance->id,
                'process_task_id' => $nextTask->id,
                'started_at' => now(),
                'execution_status' => 'pending',
            ]);

            ProcessInstanceLog::create([
                'process_instance_id' => $instance->id,
                'user_id' => 0, // Sistema
                'event' => 'task_advanced',
                'payload' => ['new_task' => $nextTask->name],
            ]);

        } else {
            // Nessun task successivo? Il processo è finito!
            $instance->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            ProcessInstanceLog::create([
                'process_instance_id' => $instance->id,
                'user_id' => 0,
                'event' => 'process_completed',
                'payload' => ['message' => 'Pratica conclusa con successo'],
            ]);
        }
    }
}
