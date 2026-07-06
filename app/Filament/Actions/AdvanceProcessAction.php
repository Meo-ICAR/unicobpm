<?php

namespace App\Filament\Actions;

use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskExecution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdvanceProcessAction
{
    public function execute(ProcessInstance $instance, Model $user, string $actionType = 'complete'): ProcessInstance
    {
        return DB::transaction(function () use ($instance, $user, $actionType) {

            // 1. Chiusura del task precedente (rimane invariata)
            $currentExecution = $instance->currentTaskExecution;
            if ($currentExecution) {
                $currentExecution->update([
                    'assignee_type' => get_class($user),
                    'assignee_id' => $user->getKey(),
                    'completed_at' => now(),
                    'execution_status' => $actionType === 'complete' ? 'completed' : 'rejected_and_rewinded',
                ]);
            }

            // 2. Calcolo del prossimo task (rimane invariata)
            $currentTask = $instance->currentTask;
            $nextTask = null;

            if ($actionType === 'complete') {
                $nextTask = ProcessTask::where('process_id', $instance->process_id)
                    ->where('order', '>', $currentTask->order)
                    ->orderBy('order', 'asc')
                    ->first();
            } else {
                $nextTask = ProcessTask::where('process_id', $instance->process_id)
                    ->where('order', '<', $currentTask->order)
                    ->orderBy('order', 'desc')
                    ->first();
            }

            // 3. Gestione del prossimo step con controllo di auto-assegnazione
            if ($nextTask) {

                $autoAssigneeId = null;
                $autoAssigneeType = null;
                $claimedAt = null;

                // Controlliamo i responsabili del nuovo task
                $responsibleAssignment = $nextTask->raciAssignments()
                    ->where('role_type', 'responsible')
                    ->first();

                if ($responsibleAssignment) {
                    $usersInFunction = User::whereHas('business_functions', function ($q) use ($responsibleAssignment) {
                        $q->where('business_functions.id', $responsibleAssignment->business_function_id);
                    })->get();

                    // Se c'è una sola persona in quel reparto, assegnazione istantanea
                    if ($usersInFunction->count() === 1) {
                        $soleUser = $usersInFunction->first();
                        $autoAssigneeId = $soleUser->id;
                        $autoAssigneeType = get_class($soleUser);
                        $claimedAt = now();
                    }
                }

                // Aggiorniamo l'istanza master con i dati (o null, o l'utente unico)
                $instance->update([
                    'current_task_id' => $nextTask->id,
                    'current_assignee_type' => $autoAssigneeType,
                    'current_assignee_id' => $autoAssigneeId,
                    'status' => 'in_progress',
                ]);

                $dueAt = $nextTask->days_to_complete
                    ? now()->addDays($nextTask->days_to_complete)
                    : null;

                // Generiamo la riga di esecuzione
                ProcessTaskExecution::create([
                    'process_instance_id' => $instance->id,
                    'process_task_id' => $nextTask->id,
                    'assignee_type' => $autoAssigneeType,
                    'assignee_id' => $autoAssigneeId,
                    'started_at' => now(),
                    'claimed_at' => $claimedAt,
                    'due_at' => $dueAt,
                    'execution_status' => 'in_progress',
                ]);

            } else {
                // Fine del processo (rimane invariata)
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
