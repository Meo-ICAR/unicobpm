<?php

namespace App\Filament\Actions;

use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskExecution;
use App\Models\User; // Assicurati di importare il modello User o chi contiene la relazione con le funzioni
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StartProcessAction
{
    public function execute(Model $subject, int $processId): ProcessInstance
    {
        return DB::transaction(function () use ($subject, $processId) {

            $process = Process::with(['tasks' => function ($q) {
                $q->orderBy('order');
            }])->findOrFail($processId);

            $firstTask = $process->tasks->first();

            // Inizializziamo le variabili di assegnazione automatica
            $autoAssigneeId = null;
            $autoAssigneeType = null;
            $claimedAt = null;

            if ($firstTask) {
                // Recuperiamo la funzione aziendale "Responsible (R)" impostata per questo task
                $responsibleAssignment = $firstTask->raciAssignments()
                    ->where('role_type', 'responsible')
                    ->first();

                if ($responsibleAssignment) {
                    // Cerchiamo quanti utenti appartengono a questa specifica funzione aziendale
                    // (Adatta questa query alla tua relazione, es: raggruppamento per business_function_id)
                    $usersInFunction = User::whereHas('business_functions', function ($q) use ($responsibleAssignment) {
                        $q->where('business_functions.id', $responsibleAssignment->business_function_id);
                    })->get();

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
            $instance = ProcessInstance::create([
                'process_id' => $process->id,
                // Se il soggetto è null, i campi polimorfi saranno null
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->getKey() : null,

                'company_id' => $subject->company_id ?? null,
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
