<?php

namespace App\Services;

use App\Mail\ProcessMail;
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
     * @param  array<int, string|array{path: string, as?: string}>  $attachments  Percorsi assoluti,
     *                                                                            o ['path' => ..., 'as' => ...] per rinominare l'allegato.
     */
    public function send(string $to, string $subject, string $body, array $attachments = [], int|string|null $companyId = null): void
    {
        $sender = $companyId === null ? null : app(SenderAccountResolver::class)->resolve($companyId, MailType::Processo);

        if ($sender !== null) {
            app(OutgoingMailer::class)->send($sender, $to, new ProcessMail($subject, $body, $attachments));

            return;
        }

        Mail::raw($body, function ($message) use ($to, $subject, $attachments) {
            $message->to($to)->subject($subject);

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
