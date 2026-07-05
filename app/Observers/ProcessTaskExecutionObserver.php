<?php

namespace App\Observers;

use App\Models\BusinessFunction;
use App\Models\Document;
use App\Models\ProcessInstanceLog;
use App\Models\ProcessTaskExecution;
use App\Models\ProcessTaskItemAnswer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * OSSERVATORE DELLE ESECUZIONI (IL BOT DI SISTEMA)
 * * Questo observer si attiva ogni volta che la pratica entra in un nuovo Task.
 * Cerca le azioni automatiche (System Tasks) e le esegue istantaneamente.
 * * ========================================================================
 * ELENCO DELLE OPZIONI SUPPORTATE (action_type) E RELATIVI JSON (config)
 * ========================================================================
 * * 1. action_type: 'automated_email'
 * Invia un'email compilando le variabili e allegando file misti.
 * JSON config:
 * {
 * "target_business_function": "IT Support",          // Codice della funzione aziendale destinataria
 * "to_email": "it-support@tuazienda.com", // Supporta variabili es: {subject.email}
 * "email_template_id": 1,                 // L'ID della tabella email_templates
 * "attach_document_types": [              // (Opzionale) Codici dei documenti caricati nella pratica da allegare
 * "carta_identita",
 * "visura_camerale"
 * ]
 * }
 * * 2. action_type: 'validation_rule'
 * Esegue un controllo di Data Quality. Se fallisce, blocca la pratica.
 * JSON config:
 * {
 * "field_to_check": "subject.partita_iva", // Il campo da validare dinamicamente
 * "min_length": 11                         // Regola: lunghezza minima
 * // In futuro puoi aggiungere: "max_length", "regex", "is_numeric", ecc.
 * }
 * * ========================================================================
 */
class ProcessTaskExecutionObserver
{
    /**
     * Quando un Task viene avviato (creato il record di esecuzione)
     */
    public function created(ProcessTaskExecution $execution): void
    {
        $instance = $execution->processInstance;
        $task = $execution->processTask;

        // Recuperiamo tutte le azioni automatiche previste in questo task
        $automatedItems = $task->items()
            ->whereIn('action_type', ['automated_email', 'validation_rule'])
            ->get();

        foreach ($automatedItems as $item) {
            $config = $item->config ?? [];

            // Smistamento logica in base al tipo di azione automatica
            switch ($item->action_type) {

                // --------------------------------------------------------
                // CASO 1: INVIO EMAIL AUTOMATICA (Con allegati misti)
                // --------------------------------------------------------
                case 'automated_email':
                    $template = $item->getEmailTemplate();
                    if (! $template) {
                        break;
                    }

                    $toEmail = null;

                    // --- NUOVA LOGICA: Risoluzione dinamica della Business Function ---
                    if (! empty($config['target_business_function'])) {
                        // Cerchiamo l'ufficio tramite il suo codice (es. 'it')
                        $department = BusinessFunction::where('code', $config['target_business_function'])->first();

                        if ($department && $department->email) {
                            $toEmail = $department->email;
                        }
                    }

                    // --- FALLBACK: Se non è una funzione aziendale, usiamo la mail diretta (es. per il cliente) ---
                    if (empty($toEmail) && ! empty($config['to_email'])) {
                        $toEmail = $item->compileTemplate($config['to_email'], $instance);
                    }

                    // Se dopo entrambi i tentativi non abbiamo un'email, interrompiamo l'azione
                    if (empty($toEmail)) {
                        Log::warning("Task ID {$item->id}: Nessuna email destinataria trovata.");
                        break;
                    }

                    // Compilazione dinamica

                    $subject = $item->compileTemplate($template->subject, $instance);
                    $body = $item->compileTemplate($template->body, $instance);

                    // Spedizione
                    if ($toEmail && $subject && $body) {
                        Mail::raw($body, function ($message) use ($toEmail, $subject, $instance, $config, $template) {
                            $message->to($toEmail)->subject($subject);

                            // A) Allegati Statici (Modulistica)
                            if (! empty($template->attachments)) {
                                foreach ($template->attachments as $staticFilePath) {
                                    $absoluteStaticPath = Storage::disk('public')->path($staticFilePath);
                                    if (file_exists($absoluteStaticPath)) {
                                        $message->attach($absoluteStaticPath);
                                    }
                                }
                            }

                            // B) Allegati Dinamici (Documenti Pratica)
                            $targetDocCodes = $config['attach_document_types'] ?? [];
                            if (! empty($targetDocCodes)) {
                                $documents = Document::whereHas('documentType', function ($query) use ($targetDocCodes) {
                                    $query->whereIn('code', $targetDocCodes);
                                })
                                    ->whereIn('id', function ($query) use ($instance) {
                                        $query->select('document_id')
                                            ->from('process_task_item_answers')
                                            ->where('process_instance_id', $instance->id)
                                            ->whereNotNull('document_id');
                                    })
                                    ->get();

                                foreach ($documents as $doc) {
                                    $absoluteDynamicPath = Storage::disk('public')->path($doc->file_path);
                                    if (file_exists($absoluteDynamicPath)) {
                                        $message->attach($absoluteDynamicPath, [
                                            'as' => $doc->name ?: 'documento_'.$doc->id,
                                            'mime' => $doc->mime_type,
                                        ]);
                                    }
                                }
                            }
                        });
                    }

                    // Registra il completamento (Scatena l'avanzamento se è l'ultima azione)
                    ProcessTaskItemAnswer::create([
                        'process_instance_id' => $instance->id,
                        'process_task_item_id' => $item->id,
                        'user_id' => 0, // Bot
                        'value_text' => "Email automatica inviata a: {$toEmail}\nOggetto: {$subject}",
                        'completed_at' => now(),
                    ]);
                    break;

                    // --------------------------------------------------------
                    // CASO 2: REGOLA DI VALIDAZIONE DATI (Data Quality)
                    // --------------------------------------------------------
                case 'validation_rule':
                    $fieldPath = $config['field_to_check'] ?? '';
                    $value = data_get($instance, $fieldPath);

                    $isValid = true;
                    $errorMessage = '';

                    // Controllo: Lunghezza minima
                    if (isset($config['min_length']) && strlen((string) $value) < $config['min_length']) {
                        $isValid = false;
                        $errorMessage = "Anomalia: Il valore di '{$fieldPath}' ({$value}) è lungo ".strlen((string) $value).' caratteri. Ne servono almeno '.$config['min_length'].'.';
                    }

                    // Se valido, chiudi l'azione. Se invalido, sospendi la pratica.
                    if ($isValid) {
                        ProcessTaskItemAnswer::create([
                            'process_instance_id' => $instance->id,
                            'process_task_item_id' => $item->id,
                            'user_id' => 0, // Bot
                            'value_text' => "Validazione superata per {$fieldPath}.",
                            'completed_at' => now(),
                        ]);
                    } else {
                        $instance->update(['status' => 'suspended']);

                        ProcessInstanceLog::create([
                            'process_instance_id' => $instance->id,
                            'user_id' => 0,
                            'event' => 'validation_failed',
                            'payload' => ['error' => $errorMessage],
                        ]);
                        // Il task rimane appeso e non si genera la answer
                    }
                    break;

                    // --------------------------------------------------------
                    // In futuro puoi aggiungere 'webhook_call', 'generate_pdf', ecc.
                    // --------------------------------------------------------
            }
        }
    }
}
