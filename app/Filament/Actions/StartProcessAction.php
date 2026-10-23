<?php

namespace App\Filament\Actions;

use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StartProcessAction
{
    /**
     * @param  array<string, mixed>  $attributes  campi dell'istanza noti all'avvio (titolo, riferimento esterno...): servono perché le azioni
     *                                            automatiche del primo passo (es. l'email) partono subito e li leggono
     */
    public function execute(?Model $subject, int $processId, array $attributes = []): ProcessInstance
    {
        return DB::transaction(function () use ($subject, $processId, $attributes) {

            $process = Process::findOrFail($processId);

            // Salta i task iniziali le cui condizioni trigger/esclusione non si applicano al
            // soggetto (es. "Verifica Visura Camerale" non serve se il soggetto è una persona fisica).
            $firstTask = ProcessTask::firstApplicableTask($process->id, $subject);

            // Inizializziamo le variabili di assegnazione automatica
            $autoAssigneeId = null;
            $autoAssigneeType = null;
            $claimedAt = null;

            if ($firstTask) {
                // Recuperiamo il ruolo "Responsible (R)" impostata per questo task
                $responsibleAssignment = $firstTask->raciAssignments()
                    ->where('raci_role', 'R')
                    ->first();

                if ($responsibleAssignment) {
                    // Cerchiamo quanti utenti ricoprono questo ruolo
                    $usersInFunction = EmployeeType::find($responsibleAssignment->employee_type_id)
                        ?->loginUsers() ?? collect();

                    // SE C'È UN SOLO UTENTE, LO ASSEGNIAMO DIRETTAMENTE
                    if ($usersInFunction->count() === 1) {
                        $soleUser = $usersInFunction->first();
                        $autoAssigneeId = $soleUser->id;
                        $autoAssigneeType = get_class($soleUser);
                        $claimedAt = now();
                    }
                }
            }

            // 1. Crea l'istanza principale (con l'assegnatario automatico se trovato)
            $instance = ProcessInstance::create($attributes + [
                'process_id' => $process->id,
                // Se il soggetto è null, i campi polimorfi saranno null
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->getKey() : null,

                'company_id' => $subject?->company_id,
                'status' => 'in_progress',
                'current_task_id' => $firstTask ? $firstTask->id : null,
                'current_assignee_type' => $autoAssigneeType,
                'current_assignee_id' => $autoAssigneeId,
                'reminders_sent_count' => 0,
            ]);

            // 2. Avvia la prima esecuzione
            if ($firstTask) {
                $dueAt = $firstTask->days_to_complete
                    ? now()->addDays($firstTask->days_to_complete)
                    : null;

                ProcessTaskExecution::create([
                    'process_instance_id' => $instance->id,
                    'process_task_id' => $firstTask->id,
                    'assignee_type' => $autoAssigneeType,
                    'assignee_id' => $autoAssigneeId,
                    'started_at' => now(),
                    'claimed_at' => $claimedAt, // Già preso in carico se auto-assegnato
                    'due_at' => $dueAt,
                    'execution_status' => 'in_progress',
                ]);
            }

            return $instance;
        });
    }
}
