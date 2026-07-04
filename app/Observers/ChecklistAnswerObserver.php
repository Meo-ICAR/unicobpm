<?php

namespace App\Observers;

use App\Models\ChecklistAnswer;
use App\Models\ProcessInstance;
use Illuminate\Support\Facades\Log;

class ChecklistAnswerObserver
{
    public function saved(ChecklistAnswer $answer): void
    {
        $item = $answer->item;

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

                    // Opzionale: Cambiamo anche lo stato del Soggetto (es. Agente)
                    $agente = $pratica->subject;
                    if ($agente) {
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
        // ... (Il resto del codice dell'Observer che avevamo scritto per gli aggiornamenti normali)
    }
}
