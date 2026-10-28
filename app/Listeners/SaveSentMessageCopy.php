<?php

namespace App\Listeners;

use App\Jobs\SaveSentMailCopy;
use Illuminate\Mail\Events\MessageSent;

/**
 * Con `mail.save_sent_copy` attivo (env MAIL_SAVE_SENT) mette in coda il salvataggio in «Posta inviata» di ogni email spedita con la
 * casella del .env, così si vede dal client di posta quello che l'app ha mandato. Le email partite da altre caselle non si toccano.
 */
class SaveSentMessageCopy
{
    public function handle(MessageSent $event): void
    {
        if (! config('mail.save_sent_copy')) {
            return;
        }

        $message = $event->sent->getOriginalMessage();
        $own = mb_strtolower((string) config('unico-core.practice_intake.env_mail_account.email_address'));
        $from = collect($message->getFrom())->map(fn ($address): string => mb_strtolower($address->getAddress()));

        if ($own === '' || ! $from->contains($own)) {
            return;
        }

        SaveSentMailCopy::dispatch($message->toString());
    }
}
