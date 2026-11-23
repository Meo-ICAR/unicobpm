<?php

namespace App\Observers;

use App\Models\ProcessInstance;
use App\Models\ProcessState;
use App\Models\ProcessTask;
use App\Models\ProcessTaskExecution;
use App\Services\ExternalAppResolver;
use App\Services\OutboundCallService;
use App\Services\WebhookDispatcher;
use Illuminate\Database\Eloquent\Relations\Relation;

class ProcessInstanceObserver
{
    /**
     * Prima che la pratica venga salvata nel database:
     * Troviamo il primo task del processo e lo impostiamo come task corrente.
     */
    public function creating(ProcessInstance $instance): void
    {
        /**
         * PROCEDURA DI CREAZIONE ISTANZA (ANTE-SALVATAGGIO):
         * Intercetta la fase preliminare al salvataggio nel database della nuova istanza.
         * Nello specifico:
         * 1. Verifica se non è già stato definito un task corrente.
         * 2. Recupera il primo task associato al processo ordinato secondo la colonna 'ordine'.
         * 3. Assegna l'ID del primo task a 'current_task_id' e imposta lo stato della pratica su 'running'.
         */
        if (! $instance->current_task_id) {
            $firstTask = $instance->process->tasks()->where('is_active', true)->orderBy('ordine')->first();

            if ($firstTask) {
                $instance->current_task_id = $firstTask->id;
                $instance->status = 'in_progress';
            }
        }

        // Stato applicativo iniziale (se il processo li definisce): il primo della fase corrente o delle successive.
        if (! $instance->process_state_id) {
            $instance->process_state_id = $this->stateFor($instance, null)?->id;
        }
    }

    /**
     * Quando la pratica passa a un'altra fase lo stato applicativo avanza al primo stato di quella fase, ma solo se lo stato attuale è di una
     * fase precedente e non è finale o di eccezione (es. «sospeso»): quelli si cambiano a mano. Dentro la stessa fase (es. LOI autorizzata,
     * in firma, sottoscritta) gli stati si cambiano a mano.
     */
    public function saving(ProcessInstance $instance): void
    {
        if (! $instance->exists || ! $instance->isDirty('current_task_id') || ! $instance->current_task_id) {
            return;
        }

        $current = $instance->process_state_id ? ProcessState::query()->with('task')->find($instance->process_state_id) : null;

        if ($current?->is_final || $current?->is_exception) {
            return;
        }

        // Lo stato attuale è già della nuova fase (o di una successiva): resta.
        $newTask = ProcessTask::find($instance->current_task_id);

        if ($current?->task !== null && $newTask !== null && $current->task->ordine >= $newTask->ordine) {
            return;
        }

        $next = $this->stateFor($instance, $current);

        if ($next !== null) {
            $instance->process_state_id = $next->id;
        }
    }

    /** Primo stato della fase corrente (o della prima fase successiva che ne ha) che segue lo stato attuale, se ce n'è uno. */
    private function stateFor(ProcessInstance $instance, ?ProcessState $current): ?ProcessState
    {
        $task = $instance->current_task_id ? ProcessTask::find($instance->current_task_id) : null;

        if ($task === null) {
            return null;
        }

        return ProcessState::query()
            ->where('process_id', $instance->process_id)
            ->whereHas('task', fn ($q) => $q->where('ordine', '>=', $task->ordine))
            ->with('task')
            ->get()
            ->sort(fn (ProcessState $a, ProcessState $b): int => [$a->task->ordine, $a->ordine] <=> [$b->task->ordine, $b->ordine])
            ->first(fn (ProcessState $s): bool => ! $s->is_final && ! $s->is_exception);
    }

    /**
     * Subito dopo che la pratica è stata creata nel database:
     * Scriviamo il primo log e apriamo l'esecuzione (la coda di lavoro).
     */
    public function created(ProcessInstance $instance): void
    {
        /**
         * PROCEDURA DI AVVIO ISTANZA (POST-SALVATAGGIO):
         * Reagisce alla avvenuta memorizzazione fisica dell'istanza del workflow.
         * Nello specifico:
         * 1. Registra l'evento di avvio nell'activity log (alizharb/filament-activity-log).
         * 2. Se è presente un task corrente ('current_task_id'), genera il primo record di esecuzione
         *    nella tabella ProcessTaskExecution con stato 'pending' per esporlo ai reparti di competenza.
         */
        // 1. Tracciamento Audit Log
        activity('bpm')
            ->causedBy(auth()->user())
            ->performedOn($instance)
            ->event('instance_started')
            ->log('Pratica avviata sulla versione '.$instance->process->version);

        // 2. Creazione della coda di esecuzione per il primo Task
        if ($instance->current_task_id) {
            ProcessTaskExecution::create([
                'process_instance_id' => $instance->id,
                'process_task_id' => $instance->current_task_id,
                'started_at' => now(),
                'execution_status' => 'pending', // In attesa che un dipartimento faccia il "Claim"
            ]);
        }
    }

    /**
     * Quando la pratica raggiunge lo stato 'completed': se il processo ha
     * `completion_write_field` configurato (es. 'stipulated_at' per
     * l'Onboarding Agente, 'dismissed_at' per l'Offboarding), chiede
     * all'applicativo esterno indicato da `completion_write_app` (default
     * 'unicofinance' — vedi config('services.apps')) di scrivere quel campo sul
     * soggetto tramite la sua API generica di scrittura. UnicoBPM non accede
     * mai direttamente al modello/tabella del soggetto per farlo, coerentemente
     * con l'API già usata per il controllo blacklist su Pratica.
     */
    public function updated(ProcessInstance $instance): void
    {
        if (! $instance->wasChanged('status')) {
            return;
        }

        $this->notifyWebhooks($instance);

        if ($instance->status !== 'completed') {
            return;
        }

        $field = $instance->process?->completion_write_field;

        if (empty($field) || ! $instance->subject_type || ! $instance->subject_id) {
            return;
        }

        // subject_type è la FQCN grezza (StartProcessAction usa get_class(), non il
        // morph map), quindi risolviamo l'alias registrato senza toccare il DB.
        $modelType = array_search($instance->subject_type, Relation::morphMap(), true) ?: null;

        if (! $modelType) {
            return;
        }

        $app = $instance->process->completion_write_app ?: ExternalAppResolver::DEFAULT_APP;

        $value = $instance->process->completion_write_value === 'now'
            ? now()->toDateString()
            : $instance->process->completion_write_value;

        // La scrittura passa dalla coda delle chiamate in uscita: se l'app non risponde non si perde, si ritenta (Chiamate in uscita).
        // Il registro attività si scrive alla consegna (vedi LogCompletionWrite).
        app(OutboundCallService::class)->enqueue(
            'completion_write', $app, 'PATCH', "/api/models/{$modelType}/{$instance->subject_id}",
            ['field' => $field, 'value' => $value],
            ['instance_id' => $instance->id, 'field' => $field, 'value' => (string) $value, 'app' => $app],
            "completion:{$instance->id}:{$field}",
        );
    }

    /** Avvisa i webhook di terzi del cambio di stato del processo. */
    private function notifyWebhooks(ProcessInstance $instance): void
    {
        app(WebhookDispatcher::class)->dispatch(WebhookDispatcher::PROCESS_STATUS_CHANGED, [
            'instance_id' => $instance->id,
            'process_code' => $instance->process?->code,
            'process_name' => $instance->process?->name,
            'status' => $instance->status,
            'previous_status' => $instance->getOriginal('status'),
            'subject_type' => array_search($instance->subject_type, Relation::morphMap(), true) ?: null,
            'subject_id' => $instance->subject_id,
        ], $instance->id.':'.$instance->status.':'.($instance->updated_at?->timestamp ?? 0));
    }
}
