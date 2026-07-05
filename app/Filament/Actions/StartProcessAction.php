<?php

namespace App\Filament\Actions;

use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StartProcessAction
{
    /**
     * Avvia una nuova istanza di processo per un dato soggetto.
     *
     * @param  Model  $subject  Il soggetto polimorfico (es. Fornitore, Cliente, ecc.)
     * @param  int  $processId  L'ID del macro processo da avviare
     */
    public function execute(Model $subject, int $processId): ProcessInstance
    {
        return DB::transaction(function () use ($subject, $processId) {
            // Recupera il template con i task ordinati
            $process = Process::with(['tasks' => function ($q) {
                $q->orderBy('order'); // Assicurati di usare il nome corretto della tua colonna di ordinamento
            }])->findOrFail($processId);

            $firstTask = $process->tasks->first();

            // 1. Crea l'istanza principale
            $instance = ProcessInstance::create([
                'process_id' => $process->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->getKey(), // Prende l'ID in modo sicuro
                'company_id' => $subject->company_id ?? null,
                'status' => 'in_progress',
                'current_task_id' => $firstTask ? $firstTask->id : null,
                'reminders_sent_count' => 0,
            ]);

            // 2. Avvia la prima esecuzione se esiste un task
            if ($firstTask) {
                $dueAt = $firstTask->days_to_complete
                    ? now()->addDays($firstTask->days_to_complete)
                    : null;

                ProcessTaskExecution::create([
                    'process_instance_id' => $instance->id,
                    'process_task_id' => $firstTask->id,
                    'started_at' => now(),
                    'due_at' => $dueAt,
                    'execution_status' => 'in_progress',
                ]);
            }

            return $instance;
        });
    }
}
