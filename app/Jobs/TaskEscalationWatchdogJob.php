<?php

namespace App\Jobs;

use App\Models\ProcessTaskExecution;
use App\Services\EscalationNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TaskEscalationWatchdogJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        /**
         * PROCEDURA DI RILEVAZIONE E GESTIONE ESCALATION TASK (WATCHDOG SLA):
         * Questo Job scansiona periodicamente i task di workflow pendenti per verificare il rispetto degli SLA (Service Level Agreement)
         * ed avviare azioni correttive (notifiche o riassegnazioni) in caso di ritardo.
         * Nello specifico:
         * 1. Recupera tutte le esecuzioni di task ancora aperte ('pending') con le relative definizioni del task e le regole di escalation associate.
         * 2. Calcola le ore trascorse dall'apertura del task (created_at).
         * 3. Ricerca nei metadati del task la regola corrispondente al livello di escalation successivo (escalation_level + 1).
         * 4. Se le ore trascorse superano la soglia specificata nella regola (delay_hours), esegue il metodo triggerEscalation()
         *    per inoltrare solleciti, notificare i manager o riassegnare d'ufficio il task, incrementando poi il livello di escalation.
         */
        // Peschiamo solo i task ancora aperti (es. non completati o non annullati)
        // Oltre ai passi «pending», contano i passi avviati ma non ancora presi in carico: sono i soli a cui si applicano le regole
        // con `until_claimed` (vedi sotto), che si fermano appena qualcuno prende in carico la pratica.
        $openExecutions = ProcessTaskExecution::query()
            ->whereNull('completed_at')
            ->whereNotIn('execution_status', ['completed', 'rejected_and_rewinded'])
            ->where(fn ($query) => $query->where('execution_status', 'pending')->orWhereHas('processInstance', fn ($instance) => $instance->where('status', 'in_progress')->whereNull('current_assignee_id')))
            ->with('processTask', 'processInstance')
            ->get();

        foreach ($openExecutions as $execution) {
            $task = $execution->processTask;
            $rules = $task->escalation_rules ?? [];

            // Rinvio concordato: l'escalation riprende dopo la data.
            if ($execution->isPostponed()) {
                continue;
            }

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

            // Regola `until_claimed`: vale solo finché nessuno ha preso in carico la pratica.
            $unclaimed = $execution->processInstance?->current_assignee_id === null;
            $isPending = $execution->execution_status === 'pending';

            if (($rule['until_claimed'] ?? false) ? ! $unclaimed : ! $isPending) {
                continue;
            }

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

        // Le email partono davvero (EscalationNotifier); senza destinatari l'escalation si registra comunque e il livello avanza.
        $sent = app(EscalationNotifier::class)->notify($execution, $rule);

        Log::warning("Escalation livello {$rule['level']} ({$rule['action']}) sul task: {$taskName}", ['sent_to' => count($sent)]);

        // FONDAMENTALE: Incrementiamo il livello per evitare loop infiniti
        $execution->increment('escalation_level');

        // Tracciamo l'evento nel log della pratica
        activity('bpm')
            ->performedOn($instance)
            ->event('escalation_triggered')
            ->withProperties(['level' => $rule['level'], 'action' => $rule['action'], 'emails_sent' => count($sent)])
            ->log("Escalation Livello {$rule['level']} sul task: {$taskName}");
    }
}
