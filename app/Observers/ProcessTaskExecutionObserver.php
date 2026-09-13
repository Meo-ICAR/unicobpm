<?php

namespace App\Observers;

use App\Models\BusinessFunction;
use App\Models\Document;
use App\Models\ProcessTaskExecution;
use App\Models\ProcessTaskItemAnswer;
use App\Services\ExternalAppResolver;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
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
 * * 3. action_type: 'blacklist_check'
 * Chiede all'applicativo esterno indicato (che possiede il dominio
 * Pratica/blacklist agenti, mai replicato qui) se l'agente della pratica è in
 * blacklist per la banca collegata. Se sì, sospende la pratica esattamente
 * come 'validation_rule'.
 * JSON config:
 * {
 * "pratica_id_field": "subject_id",  // (Opzionale, default "subject_id") campo di $instance da cui leggere l'ID pratica
 * "app": "unicoloan"                 // (Opzionale, default "unicoloan") applicativo da interrogare — vedi config('services.apps')
 * }
 * * 4. action_type: 'system_task'
 * Esegue in automatico il Job Laravel indicato in handler_job (FQCN, es. 'App\Jobs\MioJob'
 * o la classe di un job fornito da un pacchetto/applicativo esterno installato via composer).
 * Il job deve accettare nel costruttore (int $processInstanceId, array $config = []): viene
 * eseguito sincronamente (Bus::dispatchSync) così l'esito è noto subito. Se lancia un'eccezione,
 * la pratica viene sospesa esattamente come per 'validation_rule'.
 * JSON config: libero, passato per intero al job.
 * * ========================================================================
 */
class ProcessTaskExecutionObserver
{
    /**
     * Quando un Task viene avviato (creato il record di esecuzione)
     */
    public function created(ProcessTaskExecution $execution): void
    {
        /**
         * PROCEDURA DI AUTOMAZIONE TASK SYSTEM (AVVIO ESECUZIONE):
         * Questo observer intercetta l'inizio di una nuova esecuzione di task (ProcessTaskExecution)
         * per individuare ed eseguire istantaneamente tutte le azioni di sistema automatiche configurate.
         * Nello specifico:
         * 1. Recupera gli elementi di tipo 'automated_email' e 'validation_rule' legati al task corrente.
         * 2. Esegue in loop ciascuna azione automatica:
         *    - Per le email (automated_email): risolve il destinatario (Business Function o mail diretta),
         *      compila dinamicamente oggetto e testo con i placeholder del soggetto,
         *      recupera e allega documenti statici e dinamici della pratica, spedisce l'email
         *      e infine crea una ProcessTaskItemAnswer a nome del bot (user_id = 0) per marcare l'azione come completata.
         *    - Per le validazioni (validation_rule): estrae dinamicamente il valore da validare sul modello
         *      tramite 'data_get', applica le regole impostate (es: lunghezza minima) e, in caso di fallimento,
         *      sospende la pratica ('suspended') registrando l'anomalia nel log. Se valida, crea la risposta di completamento.
         */
        $instance = $execution->processInstance;
        $task = $execution->processTask;

        // Recuperiamo tutte le azioni automatiche previste in questo task
        $automatedItems = $task->processTaskItems()
            ->whereIn('action_type', ['automated_email', 'validation_rule', 'blacklist_check', 'system_task'])
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
                                // Document vive su una connessione DB separata (mysql_unicooam): recuperiamo prima
                                // gli ID sulla connessione locale, per poi filtrare i Document senza subquery cross-DB.
                                $documentIds = ProcessTaskItemAnswer::where('process_instance_id', $instance->id)
                                    ->whereNotNull('document_id')
                                    ->pluck('document_id');

                                $documents = Document::whereHas('documentType', function ($query) use ($targetDocCodes) {
                                    $query->whereIn('code', $targetDocCodes);
                                })
                                    ->whereIn('id', $documentIds)
                                    ->get();

                                foreach ($documents as $doc) {
                                    $absoluteDynamicPath = Storage::disk('public')->path($doc->document_url);
                                    if (file_exists($absoluteDynamicPath)) {
                                        $message->attach($absoluteDynamicPath, [
                                            'as' => $doc->name ?: 'documento_'.$doc->id,
                                        ]);
                                    }
                                }
                            }
                        });
                    }

                    // Registra il completamento (Scatena l'avanzamento se è l'ultima azione)
                    ProcessTaskItemAnswer::create([
                        'process_instance_id' => $instance->id,
                        'process_task_execution_id' => $execution->id,
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
                            'process_task_execution_id' => $execution->id,
                            'process_task_item_id' => $item->id,
                            'user_id' => 0, // Bot
                            'value_text' => "Validazione superata per {$fieldPath}.",
                            'completed_at' => now(),
                        ]);
                    } else {
                        $instance->update(['status' => 'suspended']);

                        activity('bpm')
                            ->performedOn($instance)
                            ->event('validation_failed')
                            ->withProperties(['error' => $errorMessage])
                            ->log($errorMessage);
                        // Il task rimane appeso e non si genera la answer
                    }
                    break;

                    // --------------------------------------------------------
                    // CASO 3: VERIFICA BLACKLIST AGENTE (chiede a UnicoLoan)
                    // --------------------------------------------------------
                case 'blacklist_check':
                    $praticaIdField = $config['pratica_id_field'] ?? 'subject_id';
                    $praticaId = data_get($instance, $praticaIdField);
                    $app = $config['app'] ?? ExternalAppResolver::DEFAULT_APP;

                    $blacklisted = false;

                    if (! empty($praticaId)) {
                        try {
                            $response = Http::asJson()
                                ->timeout(8)
                                ->connectTimeout(4)
                                ->get(app(ExternalAppResolver::class)->urlFor($app)."/api/pratiche/{$praticaId}");

                            $blacklisted = $response->successful() && $response->json('agente_blacklistato') === true;
                        } catch (\Throwable $e) {
                            Log::warning("Task ID {$item->id}: verifica blacklist su {$app} fallita per errore di rete.", [
                                'pratica_id' => $praticaId,
                            ]);
                        }
                    }

                    if ($blacklisted) {
                        $instance->update(['status' => 'suspended']);

                        activity('bpm')
                            ->performedOn($instance)
                            ->event('blacklist_check_failed')
                            ->withProperties(['pratica_id' => $praticaId])
                            ->log("Pratica sospesa: l'agente è in blacklist per la banca collegata.");
                        // Il task rimane appeso e non si genera la answer
                    } else {
                        ProcessTaskItemAnswer::create([
                            'process_instance_id' => $instance->id,
                            'process_task_execution_id' => $execution->id,
                            'process_task_item_id' => $item->id,
                            'user_id' => 0, // Bot
                            'value_text' => 'Verifica blacklist superata: agente non bloccato per questa banca.',
                            'completed_at' => now(),
                        ]);
                    }
                    break;

                    // --------------------------------------------------------
                    // CASO 4: JOB DI SISTEMA (locale o da pacchetto/applicativo esterno)
                    // --------------------------------------------------------
                case 'system_task':
                    $jobClass = $item->handler_job;

                    if (blank($jobClass) || ! class_exists($jobClass)) {
                        Log::warning("Task ID {$item->id}: handler_job \"{$jobClass}\" non trovato o non configurato.");
                        break;
                    }

                    try {
                        Bus::dispatchSync(new $jobClass($instance->id, $config));

                        ProcessTaskItemAnswer::create([
                            'process_instance_id' => $instance->id,
                            'process_task_execution_id' => $execution->id,
                            'process_task_item_id' => $item->id,
                            'user_id' => 0, // Bot
                            'value_text' => "Job eseguito con successo: {$jobClass}",
                            'completed_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        $instance->update(['status' => 'suspended']);

                        activity('bpm')
                            ->performedOn($instance)
                            ->event('system_task_failed')
                            ->withProperties(['job' => $jobClass, 'error' => $e->getMessage()])
                            ->log("Job di sistema fallito ({$jobClass}): {$e->getMessage()}");
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
