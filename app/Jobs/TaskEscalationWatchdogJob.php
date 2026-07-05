<?php

namespace App\Jobs;

use App\Models\ProcessInstanceLog;
use App\Models\ProcessTaskExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskEscalationWatchdogJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        // Peschiamo solo i task ancora aperti (es. non completati o non annullati)
        $openExecutions = ProcessTaskExecution::where('status', 'pending')->with('processTask')->get();

        foreach ($openExecutions as $execution) {
            $task = $execution->processTask;
            $rules = $task->escalation_rules ?? [];

            if (empty($rules)) {
                continue;
            }

            // Identifichiamo la regola per il PROSSIMO livello di escalation
            $nextLevel = $execution->escalation_level + 1;

            // Cerchiamo la regola corrispondente nell'array JSON
            $rule = collect($rules)->firstWhere('level', $nextLevel);

            if (! $rule) {
                continue;
            } // Abbiamo esaurito i livelli di escalation per questo task

            // Calcoliamo da quante ore è aperto questo task
            $hoursOpen = $execution->created_at->diffInHours(now());

            // Se il task è aperto da più ore rispetto al limite della regola, SCATTA L'ESCALATION!
            if ($hoursOpen >= $rule['delay_hours']) {
                $this->triggerEscalation($execution, $rule);
            }
        }
    }

    private function triggerEscalation(ProcessTaskExecution $execution, array $rule): void
    {
        $instance = $execution->processInstance;
        $taskName = $execution->processTask->name;

        switch ($rule['action']) {
            case 'notify_assignee':
                // Logica per avvisare chi ce l'ha in carico (es. Ufficio assegnatario)
                Log::info("Sollecito Livello 1 inviato per Task: {$taskName}");
                // Esempio: Mail::raw("Il task {$taskName} è in ritardo...", ...);
                break;

            case 'notify_manager':
                // Logica per avvisare un supervisore
                $managerEmail = $rule['manager_email'] ?? 'admin@azienda.com';
                Log::warning("Escalation Livello 2: Task {$taskName} segnalato a {$managerEmail}");
                // Invia email al manager...
                break;

            case 'reassign':
                // Riassegnazione drastica a un altro dipartimento
                $newDept = $rule['target_business_function'];
                // Assumiamo che ci sia un campo 'assigned_to_business_function' su ProcessTaskExecution
                // $execution->update(['assigned_to_business_function' => $newDept]);
                Log::error("Escalation Livello 3: Task {$taskName} tolto all'assegnatario e passato a {$newDept}");
                break;
        }

        // FONDAMENTALE: Incrementiamo il livello per evitare loop infiniti
        $execution->increment('escalation_level');

        // Tracciamo l'evento nel log della pratica
        ProcessInstanceLog::create([
            'process_instance_id' => $instance->id,
            'user_id' => 0, // Bot
            'event' => 'escalation_triggered',
            'payload' => ['level' => $rule['level'], 'action' => $rule['action']],
        ]);
    }
}
