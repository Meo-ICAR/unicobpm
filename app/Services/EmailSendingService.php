<?php

namespace App\Services;

use App\Mail\ProcessMail;
use App\Support\ProcessThread;
use Illuminate\Support\Facades\Mail;
use Unico\Core\Enums\MailType;
use Unico\Core\Mail\OutgoingMailer;
use Unico\Core\Mail\SenderAccountResolver;

/**
 * Invio email grezzo (senza Mailable/Notification, coerente con l'uso esistente di
 * Mail::raw nel motore BPM) condiviso tra il bot di sistema (ProcessTaskExecutionObserver,
 * azione 'automated_email') e la conferma umana delle bozze preparate dall'assistente AI.
 */
class EmailSendingService
{
    /**
     * Se la company ha una casella di tipo «processo» con SMTP (mail_accounts) l'email parte da lì, e le risposte tornano a quella
     * casella; altrimenti dal mailer di default.
     *
     * Con `$thread` l'email parte con un Message-ID che identifica il processo (istanza ed esecuzione): la risposta che lo riporta
     * si ricollega da sola al processo (HandleProcessEmailReply).
     *
     * @param  array<int, string|array{path: string, as?: string}>  $attachments  Percorsi assoluti,
     *                                                                            o ['path' => ..., 'as' => ...] per rinominare l'allegato.
     * @param  array{instance: int, execution?: ?int}|null  $thread
     */
    public function send(string $to, string $subject, string $body, array $attachments = [], int|string|null $companyId = null, ?array $thread = null): void
    {
        $messageId = $thread === null ? null : ProcessThread::messageId($thread['instance'], $thread['execution'] ?? null);
        $sender = $companyId === null ? null : app(SenderAccountResolver::class)->resolve($companyId, MailType::Processo);

        if ($sender !== null) {
            app(OutgoingMailer::class)->send($sender, $to, new ProcessMail($subject, $body, $attachments, $messageId));

            return;
        }

        Mail::raw($body, function ($message) use ($to, $subject, $attachments, $messageId) {
            $message->to($to)->subject($subject);

            if ($messageId !== null) {
                $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $messageId);
            }

            foreach ($attachments as $attachment) {
                $path = is_array($attachment) ? ($attachment['path'] ?? null) : $attachment;

                if (! $path || ! file_exists($path)) {
                    continue;
                }

                $options = is_array($attachment) && isset($attachment['as'])
                    ? ['as' => $attachment['as']]
                    : [];

                $message->attach($path, $options);
            }
        });
    }
}
