<?php

namespace App\Console\Commands;

use App\Models\ProcessInstance;
use App\Services\DocumentClassifier;
use App\Services\Replies\ProcessAttachmentIngestor;
use Illuminate\Console\Command;
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

        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            $attachments[] = ['name' => $attachment->getName(), 'content' => $attachment->getContent()];
        }

        app(ProcessAttachmentIngestor::class)->ingest($pratica, $execution, $attachments, $message->getFrom()[0]->mail);
    }
}
