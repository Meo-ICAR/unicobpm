<?php

namespace App\Observers;

use App\Models\ChecklistAnswer;
use App\Models\ChecklistItem;
use App\Models\ProcessInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ChecklistAnswerObserver
{
    public function saved(ChecklistAnswer $answer): void
    {
        /**
         * PROCEDURA DI ELABORAZIONE RISPOSTE CHECKLIST (SALVATAGGIO):
         * Questo observer reagisce al salvataggio delle risposte alle domande di controllo (checklist).
         * Nello specifico:
         * 1. Verifica se la domanda fa parte di una regola Knockout (KO) ('is_knockout' = true).
         * 2. Compara la risposta fornita dall'utente (boolean o testuale) con il valore configurato come KO.
         * 3. In caso di corrispondenza KO, imposta lo stato della ProcessInstance (pratica) a 'rejected',
         *    marchia la data di completamento e opzionalmente respinge anche l'anagrafica del soggetto collegato.
         * 4. Registra l'evento KO nel canale log dedicato ('bpm') a scopo di audit.
         * 5. Se la risposta non attiva regole di KO, procede con il fluire standard del processo.
         */
        $item = $answer->checklistItem;

        // --- 1. CONTROLLO KNOCKOUT (KO) ---
        if ($item->is_knockout) {
            // Convertiamo in stringa per fare un confronto sicuro (es. '0' == '0')
            $userAnswer = $answer->value_boolean !== null
                ? (string) (int) $answer->value_boolean
                : (string) $answer->value_text;

            if ($userAnswer === (string) $item->knockout_value) {
                // Recuperiamo la pratica associata (ProcessInstance)
                // (Assumendo che la risposta abbia un riferimento diretto o indiretto alla pratica)
                $pratica = ProcessInstance::find($answer->process_instance_id);

                if ($pratica && $pratica->status !== 'rejected') {
                    // Chiudiamo la pratica
                    $pratica->status = 'rejected';
                    $pratica->completed_at = now();
                    $pratica->save();

                    // Opzionale: Cambiamo anche lo stato del Soggetto (es. Agente), solo se il suo
                    // modello ha davvero una colonna "status" (il soggetto è polimorfo: Fornitore,
                    // Employee, Client... non tutti condividono lo stesso schema).
                    $agente = $pratica->subject;
                    if ($agente && $this->hasColumn($agente, 'status')) {
                        $agente->status = 'rejected';
                        $agente->save();
                    }

                    // Scriviamo nel Log per l'Audit
                    Log::channel('bpm')->info("Pratica {$pratica->id} respinta per KO sulla domanda: {$item->name}");

                    // Poiché il processo è morto, possiamo fermare altre logiche dell'observer
                    return;
                }
            }
        }

        // --- 2. LOGICHE STANDARD (Target Model / Field) ---
        // Se configurato, la risposta "setta" un dato sull'anagrafica del soggetto della pratica:
        // o marca un timestamp (is_timestamp_update, es. "verificato il ..."), oppure scrive
        // direttamente il valore della risposta nel campo indicato.
        $this->writeBackToSubject($answer, $item);
    }

    /**
     * Scrive (se configurato) il valore della risposta, o un timestamp, nel campo indicato da
     * trigger_field sul soggetto della pratica. trigger_model è una guardia opzionale: se
     * valorizzato, la scrittura avviene solo se il soggetto è effettivamente di quel tipo
     * (accetta sia il nome di classe completo che l'alias del morphMap).
     */
    protected function writeBackToSubject(ChecklistAnswer $answer, ChecklistItem $item): void
    {
        if (blank($item->trigger_field)) {
            return;
        }

        $pratica = $answer->processInstance ?? ProcessInstance::find($answer->process_instance_id);
        $subject = $pratica?->subject;

        if (! $subject instanceof Model) {
            return;
        }

        if (filled($item->trigger_model) && ! $this->subjectMatchesTriggerModel($subject, $item->trigger_model)) {
            return;
        }

        $field = $item->trigger_field;

        if (! $this->hasColumn($subject, $field)) {
            Log::channel('bpm')->warning(
                "ChecklistItem #{$item->id}: trigger_field \"{$field}\" non esiste su ".get_class($subject).', scrittura ignorata.'
            );

            return;
        }

        $subject->{$field} = $item->is_timestamp_update
            ? now()
            : ($answer->value_boolean ?? $answer->value_text);

        $subject->save();

        Log::channel('bpm')->info(
            "Pratica {$pratica->id}: risposta a \"{$item->name}\" ha aggiornato {$field} su ".get_class($subject)." #{$subject->getKey()}"
        );
    }

    protected function subjectMatchesTriggerModel(Model $subject, string $triggerModel): bool
    {
        if (get_class($subject) === $triggerModel) {
            return true;
        }

        return Relation::getMorphedModel($triggerModel) === get_class($subject);
    }

    /**
     * Verifica in modo sicuro se un modello (potenzialmente su una connessione diversa da quella
     * di default, es. Fornitore su "proforma") possiede davvero la colonna indicata.
     */
    protected function hasColumn(Model $model, string $column): bool
    {
        $table = Str::afterLast($model->getTable(), '.');

        return Schema::connection($model->getConnectionName())->hasColumn($table, $column);
    }
}
