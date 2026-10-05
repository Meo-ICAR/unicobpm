<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

/**
 * Invio email grezzo (senza Mailable/Notification, coerente con l'uso esistente di
 * Mail::raw nel motore BPM) condiviso tra il bot di sistema (ProcessTaskExecutionObserver,
 * azione 'automated_email') e la conferma umana delle bozze preparate dall'assistente AI.
 */
class EmailSendingService
{
    /**
     * @param  array<int, string|array{path: string, as?: string}>  $attachments  Percorsi assoluti,
     *                                                                            o ['path' => ..., 'as' => ...] per rinominare l'allegato.
     */
    public function send(string $to, string $subject, string $body, array $attachments = []): void
    {
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
