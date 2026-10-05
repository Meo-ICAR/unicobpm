<?php

namespace App\Jobs;

use App\Models\BusinessFunction;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Sorveglia i termini TASSATIVI (facoltativi, indipendenti dalle
 * escalation_rules già gestite da TaskEscalationWatchdogJob):
 * - ProcessInstance.hard_deadline_at: data tassativa di fine dell'intera pratica.
 * - ProcessTaskExecution.mandatory_days_to_complete: giorni tassativi entro
 *   cui il singolo step deve essere completato.
 *
 * Per ciascun termine scaduto, individua l'utente Responsible (RACI 'R') del
 * task corrente e registra il sollecito nell'activity log. Non esiste ancora
 * un canale email reale in questo codebase (vedi BPM-DOMAIN-SPEC.md §4): qui
 * ci si ferma al log/activity, pronto per essere agganciato a un Mailable
 * quando quell'infrastruttura sarà completata.
 */
class MandatoryDeadlineWatchdogJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $this->checkStepDeadlines();
        $this->checkInstanceDeadlines();
    }

    private function checkStepDeadlines(): void
    {
        $openExecutions = ProcessTaskExecution::whereNotIn('execution_status', ['completed', 'rejected_and_rewinded'])
            ->whereNotNull('mandatory_days_to_complete')
            ->with('processTask', 'processInstance')
            ->get();

        foreach ($openExecutions as $execution) {
            if (! $execution->isOverdue()) {
                continue;
            }

            $this->remind(
                instance: $execution->processInstance,
                task: $execution->processTask,
                event: 'mandatory_step_deadline_reached',
                message: "Termine tassativo superato per lo step '{$execution->processTask->name}' "
                    ."({$execution->mandatory_days_to_complete} giorni dall'avvio).",
            );
        }
    }

    private function checkInstanceDeadlines(): void
    {
        $instances = ProcessInstance::where('status', 'in_progress')
            ->whereNotNull('hard_deadline_at')
            ->with('currentTask')
            ->get();

        foreach ($instances as $instance) {
            if (! $instance->isPastHardDeadline()) {
                continue;
            }

            $this->remind(
                instance: $instance,
                task: $instance->currentTask,
                event: 'mandatory_instance_deadline_reached',
                message: "Data tassativa di termine della pratica superata ({$instance->hard_deadline_at->toDateString()}).",
            );
        }
    }

    private function remind(ProcessInstance $instance, $task, string $event, string $message): void
    {
        $recipients = collect();

        if ($task) {
            $responsible = $task->raciAssignments()->where('raci_role', 'R')->first();

            if ($responsible) {
                $recipients = BusinessFunction::find($responsible->business_function_id)?->loginUsers() ?? collect();
            }
        }

        if ($instance->process?->include_eligible_count_in_reminders) {
            $count = $instance->process->eligibleRecordsCount();

            if ($count !== null) {
                $message .= " Record attualmente eleggibili per questo processo: {$count}.";
            }
        }

        Log::warning($message, ['instance_id' => $instance->id, 'recipients' => $recipients->pluck('id')->all()]);

        activity('bpm')
            ->performedOn($instance)
            ->event($event)
            ->withProperties(['recipients' => $recipients->pluck('id')->all()])
            ->log($message);
    }
}
