<?php

namespace App\Listeners;

use App\Models\ProcessEmailReply;
use App\Services\Replies\ProcessAttachmentIngestor;
use App\Services\Replies\ProcessReplyApplier;
use App\Services\Replies\ProcessReplyMatcher;
use App\Services\Replies\ReplyInterpreter;
use App\Services\Replies\ReplyText;
use Illuminate\Contracts\Queue\ShouldQueue;
use Unico\Core\Events\IncomingEmailReceived;
use Unico\Core\Mail\RuleBasedClassifier;
use Unico\Core\Models\IncomingEmail;

/**
 * Una email arriva in una casella: se risponde a un'email di un processo (identificativo di conversazione) la si legge e il
 * processo avanza: gli allegati soddisfano le azioni di caricamento, il testo risponde all'azione «attesa risposta email»
 * (interpretato da regole e, se serve, dall'AI). Quel che non è sicuro lo decide un operatore (Risposte email).
 * Ogni email si elabora una volta sola e lascia una riga con l'interpretazione e l'esito.
 */
class HandleProcessEmailReply implements ShouldQueue
{
    public function __construct(
        private ProcessReplyMatcher $matcher,
        private ReplyInterpreter $interpreter,
        private ProcessReplyApplier $applier,
        private ProcessAttachmentIngestor $attachments,
    ) {}

    public function handle(IncomingEmailReceived $event): void
    {
        $email = $event->email;

        // Rimbalzi e avvisi automatici del provider non sono risposte di una persona.
        if (in_array($email->classification, [RuleBasedClassifier::BOUNCE, RuleBasedClassifier::PROVIDER_NOTIFICATION, RuleBasedClassifier::SPAM], true)) {
            return;
        }

        $match = $this->matcher->match($email);

        if ($match === null || ProcessEmailReply::query()->where('incoming_email_id', $email->id)->exists()) {
            return;
        }

        ['instance' => $instance, 'execution' => $execution, 'current' => $current] = $match;
        $text = ReplyText::clean($email->body_text ?: strip_tags((string) $email->body_html));

        $reply = ProcessEmailReply::create([
            'incoming_email_id' => $email->id,
            'process_instance_id' => $instance->id,
            'process_task_execution_id' => $execution?->id,
            'from_email' => (string) $email->from_email,
            'subject' => mb_substr((string) $email->subject, 0, 500),
            'excerpt' => mb_substr($text, 0, 1000),
            'status' => ProcessEmailReply::NO_ACTION,
        ]);

        if (! $current || $execution === null) {
            return; // risposta a un passo già concluso: registrata, non applicata
        }

        $this->attachments->ingest($instance, $execution, $this->attachmentsOf($email), (string) $email->from_email);

        $item = $this->applier->pendingItem($instance, $execution->id);

        if ($item === null || $text === '') {
            return;
        }

        $interpretation = $this->interpreter->interpret($text, ($item->config['question'] ?? null) ?: $item->name);

        $reply->update([
            'process_task_item_id' => $item->id,
            'intent' => $interpretation?->intent,
            'confidence' => $interpretation?->confidence,
            'summary' => $interpretation?->summary,
            'proposed_date' => $interpretation?->date,
            'source' => $interpretation?->source,
            'status' => ProcessEmailReply::NEEDS_REVIEW,
        ]);

        $how = $this->applier->decide($interpretation, $item);

        if ($how === ProcessReplyApplier::REVIEW) {
            activity('bpm')->performedOn($instance)->event('email_reply_needs_review')
                ->withProperties(['intent' => $interpretation?->intent, 'confidence' => $interpretation?->confidence])
                ->log('Risposta email da rivedere.');

            return;
        }

        $this->applier->apply($reply->refresh(), $how);
    }

    /** @return list<array{name: string, content: string}> */
    private function attachmentsOf(IncomingEmail $email): array
    {
        return $email->getMedia(IncomingEmail::ATTACHMENTS)->map(fn ($media): array => [
            'name' => $media->file_name,
            'content' => (string) stream_get_contents($media->stream()),
        ])->all();
    }
}
