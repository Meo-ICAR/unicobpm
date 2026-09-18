<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use App\Services\DocumentClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;

class ProcessIncomingEmails extends Command
{
    protected $signature = 'bpm:read-emails';

    protected $description = 'Legge la casella email dedicata ed estrae i documenti per il BPM';

    public function __construct(protected DocumentClassifier $documentClassifier)
    {
        parent::__construct();
    }

    public function handle()
    {
        /**
         * PROCEDURA DI ELABORAZIONE EMAIL IN INGRESSO:
         * Questo comando legge in background la casella di posta dedicata per integrare l'acquisizione documentale automatica.
         * Nello specifico:
         * 1. Stabilisce una connessione IMAP ed interroga la cartella INBOX per estrarre tutti i messaggi non letti.
         * 2. Tramite espressione regolare sul subject dell'email, ricerca un pattern del tipo "[ID: X]" per identificare la pratica (ProcessInstance) associata.
         * 3. Se la pratica viene trovata ed è in corso ('in_progress'), delega l'estrazione e il salvataggio degli allegati al metodo helper.
         * 4. Al termine del processamento, contrassegna l'email come letta ('Seen') per escluderla dalle successive esecuzioni.
         */
        // 1. Ci colleghiamo alla casella email configurata
        $client = Client::account('default');
        $client->connect();

        // 2. Prendiamo solo le email non lette nella INBOX
        $folder = $client->getFolder('INBOX');
        $messages = $folder->query()->unseen()->get();

        foreach ($messages as $message) {
            $subject = $message->getSubject();

            // 3. Estraggo l'ID della pratica dall'oggetto usando una Regex (es. cerca [ID: X])
            if (preg_match('/\[ID:\s*(\d+)\]/', $subject, $matches)) {
                $praticaId = $matches[1];
                $pratica = ProcessInstance::find($praticaId);

                if ($pratica && $pratica->status === 'in_progress') {
                    $this->processEmailAttachments($message, $pratica);
                }
            }

            // 4. Segno la mail come letta (o la sposto) così non la rielaboro al prossimo giro
            $message->setFlag('Seen');
        }
    }

    private function processEmailAttachments($message, ProcessInstance $pratica)
    {
        $execution = $pratica->currentTaskExecution;

        if (! $execution || ! $message->hasAttachments()) {
            return;
        }

        // Item di upload ancora senza risposta sul task corrente: possono essere più di uno
        // (es. "Carica Visura" + "Carica Documento Identità"), quindi ogni allegato va capito
        // singolarmente invece di assumere che sia sempre il primo item della lista.
        $pendingItems = $execution->pendingItemsOfType(['document_upload']);

        if ($pendingItems->isEmpty()) {
            return;
        }

        foreach ($message->getAttachments() as $attachment) {
            // Salvo il file fisicamente nel mio storage locale o S3
            $path = 'documents/'.uniqid().'_'.$attachment->getName();
            Storage::disk('public')->put($path, $attachment->getContent());

            // Capisco a quale item l'allegato corrisponde: prima con regex, poi con AI.
            $classification = $this->documentClassifier->classify($pendingItems, $path, $attachment->getName());
            $item = $classification['item'];

            // Creo comunque il record nella tabella centrale dei Documenti (DMS), anche se
            // non è stato possibile classificarlo: resta visibile per un controllo manuale
            // invece di sparire o essere assegnato a caso.
            $document = Document::create([
                'document_type_id' => $item?->document_type_id,
                'documentable_type' => $pratica->subject_type,
                'documentable_id' => $pratica->subject_id,
                'document_url' => $path,
                'name' => $attachment->getName(),
                'ai_abstract' => $classification['abstract'],
                'ai_confidence_score' => $classification['confidence'],
            ]);

            if (! $item) {
                continue;
            }

            // Salvo la risposta nel workflow a nome dell'operatore virtuale (bot procedurale
            // o AI) che ha capito l'allegato.
            ProcessTaskItemAnswer::create([
                'process_instance_id' => $pratica->id,
                'process_task_execution_id' => $execution->id,
                'process_task_item_id' => $item->id,
                'document_id' => $document->id,
                'value_text' => 'Documento ricevuto via Email da: '.$message->getFrom()[0]->mail,
                'user_id' => 0,
                'operator_type' => $classification['operator_type'],
                'operator_label' => $classification['operator_type'] === 'ai' ? 'email_ingestion.ai' : 'email_ingestion.regex',
                'confidence' => $classification['confidence'],
                'completed_at' => now(),
            ]);

            // NOTA: Avendo fatto la ::create() qui sopra, scatta in automatico il tuo
            // ProcessTaskItemAnswerObserver che verificherà se il task è completo
            // e farà avanzare la pratica al prossimo step!

            // L'item appena soddisfatto non è più un candidato per gli allegati successivi
            // della stessa email.
            $pendingItems = $pendingItems->reject(
                fn (ProcessTaskItem $candidate) => $candidate->id === $item->id
            )->values();

            if ($pendingItems->isEmpty()) {
                break;
            }
        }
    }
}
