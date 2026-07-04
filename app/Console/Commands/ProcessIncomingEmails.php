<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use Illuminate\Console\Command;
use Webklex\IMAP\Facades\Client;

class ProcessIncomingEmails extends Command
{
    protected $signature = 'bpm:read-emails';

    protected $description = 'Legge la casella email dedicata ed estrae i documenti per il BPM';

    public function handle()
    {
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
        // Troviamo qual è l'azione di upload richiesta nel task attuale
        $taskItem = ProcessTaskItem::where('process_task_id', $pratica->current_task_id)
            ->where('action_type', 'document_upload')
            ->first();

        if (! $taskItem) {
            return;
        }

        // Se l'email ha degli allegati
        if ($message->hasAttachments()) {
            foreach ($message->getAttachments() as $attachment) {

                // Salvo il file fisicamente nel mio storage locale o S3
                $path = 'documents/'.uniqid().'_'.$attachment->getName();
                \Storage::disk('public')->put($path, $attachment->getContent());

                // 1. Creo il record nella tabella centrale dei Documenti (DMS)
                $document = Document::create([
                    'document_type_id' => $taskItem->document_type_id,
                    'subject_type' => $pratica->subject_type,
                    'subject_id' => $pratica->subject_id,
                    'file_path' => $path,
                    'name' => $attachment->getName(),
                ]);

                // 2. Salvo la risposta nel workflow a nome dell'Utente Bot (ID 0)
                ProcessTaskItemAnswer::create([
                    'process_instance_id' => $pratica->id,
                    'process_task_item_id' => $taskItem->id,
                    'document_id' => $document->id,
                    'value_text' => 'Documento ricevuto via Email da: '.$message->getFrom()[0]->mail,
                    'user_id' => 0,
                    'completed_at' => now(),
                ]);

                // NOTA: Avendo fatto la ::create() qui sopra, scatta in automatico il tuo
                // ProcessTaskItemAnswerObserver che verificherà se il task è completo
                // e farà avanzare la pratica al prossimo step!

                break; // Usciamo se accettiamo un solo allegato per volta, o gestiamo gli altri
            }
        }
    }
}
