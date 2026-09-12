<?php

namespace App\Observers;

use App\Models\ProcessInstance;
use App\Models\ProcessTaskExecution;
use App\Services\ExternalAppResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            $firstTask = $instance->process->tasks()->orderBy('ordine')->first();

            if ($firstTask) {
                $instance->current_task_id = $firstTask->id;
                $instance->status = 'in_progress';
            }
        }
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
     * 'unicoloan' — vedi config('services.apps')) di scrivere quel campo sul
     * soggetto tramite la sua API generica di scrittura. UnicoBPM non accede
     * mai direttamente al modello/tabella del soggetto per farlo, coerentemente
     * con l'API già usata per il controllo blacklist su Pratica.
     */
    public function updated(ProcessInstance $instance): void
    {
        if (! $instance->wasChanged('status') || $instance->status !== 'completed') {
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
        $appResolver = app(ExternalAppResolver::class);

        $value = $instance->process->completion_write_value === 'now'
            ? now()->toDateString()
            : $instance->process->completion_write_value;

        try {
            $response = Http::asJson()
                ->timeout(8)
                ->connectTimeout(4)
                ->patch("{$appResolver->urlFor($app)}/api/models/{$modelType}/{$instance->subject_id}", [
                    'field' => $field,
                    'value' => $value,
                ]);

            if ($response->failed()) {
                Log::warning("Scrittura completamento su {$app} fallita.", [
                    'model_type' => $modelType,
                    'subject_id' => $instance->subject_id,
                    'field' => $field,
                    'status' => $response->status(),
                ]);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning("Scrittura completamento su {$app} fallita per errore di rete.", [
                'model_type' => $modelType,
                'subject_id' => $instance->subject_id,
                'field' => $field,
            ]);

            return;
        }

        activity('bpm')
            ->performedOn($instance)
            ->event('completion_field_written')
            ->withProperties(['field' => $field, 'value' => (string) $value, 'app' => $app])
            ->log("Scritto {$field} sul soggetto della pratica al completamento (via API {$app}).");
    }
}
