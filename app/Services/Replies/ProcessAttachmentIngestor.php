<?php

namespace App\Services\Replies;

use App\Models\Document;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskExecution;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use App\Services\DocumentClassifier;
use Illuminate\Support\Facades\Storage;

/**
 * Allegati ricevuti per email → documenti del processo. Ogni allegato si abbina a un'azione di caricamento ancora senza risposta
 * (prima per regex, poi con l'AI: DocumentClassifier); uno non riconosciuto resta comunque tra i documenti per il controllo manuale.
 * Le risposte create fanno avanzare il task, come per qualsiasi altro caricamento.
 */
class ProcessAttachmentIngestor
{
    public function __construct(private DocumentClassifier $classifier) {}

    /**
     * @param  iterable<array{name: string, content: string}>  $attachments
     * @return int quante azioni di caricamento sono state soddisfatte
     */
    public function ingest(ProcessInstance $instance, ProcessTaskExecution $execution, iterable $attachments, string $from): int
    {
        // Più azioni di caricamento possono essere in attesa (es. visura + documento d'identità): ogni allegato va capito singolarmente.
        $pendingItems = $execution->pendingItemsOfType(['document_upload']);

        if ($pendingItems->isEmpty()) {
            return 0;
        }

        $answered = 0;

        foreach ($attachments as $attachment) {
            $path = 'documents/'.uniqid().'_'.$attachment['name'];
            Storage::disk('public')->put($path, $attachment['content']);

            $classification = $this->classifier->classify($pendingItems, $path, $attachment['name']);
            $item = $classification['item'];

            $document = Document::create([
                'document_type_id' => $item?->document_type_id,
                'documentable_type' => $instance->subject_type,
                'documentable_id' => $instance->subject_id,
                'document_url' => $path,
                'name' => $attachment['name'],
                'ai_abstract' => $classification['abstract'],
                'ai_confidence_score' => $classification['confidence'],
            ]);

            if (! $item) {
                continue;
            }

            ProcessTaskItemAnswer::create([
                'process_instance_id' => $instance->id,
                'process_task_execution_id' => $execution->id,
                'process_task_item_id' => $item->id,
                'document_id' => $document->id,
                'value_text' => 'Documento ricevuto via Email da: '.$from,
                'user_id' => 0,
                'operator_type' => $classification['operator_type'],
                'operator_label' => $classification['operator_type'] === 'ai' ? 'email_ingestion.ai' : 'email_ingestion.regex',
                'confidence' => $classification['confidence'],
                'completed_at' => now(),
            ]);

            $answered++;
            $pendingItems = $pendingItems->reject(fn (ProcessTaskItem $candidate) => $candidate->id === $item->id)->values();

            if ($pendingItems->isEmpty()) {
                break;
            }
        }

        return $answered;
    }
}
