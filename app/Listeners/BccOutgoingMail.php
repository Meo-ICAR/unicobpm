<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Copia nascosta di ogni email in uscita agli indirizzi di `mail.always_bcc` (env MAIL_ALWAYS_BCC, separati da virgola), così chi
 * amministra l'app ha sotto gli occhi quello che parte. Non duplica chi è già destinatario del messaggio. Vale per tutti i mailer,
 * anche per le caselle aziendali di `OutgoingMailer`.
 */
class BccOutgoingMail
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        $already = collect([...$message->getTo(), ...$message->getCc(), ...$message->getBcc()])
            ->map(fn (Address $address): string => mb_strtolower($address->getAddress()));

        $copies = collect(explode(',', (string) config('mail.always_bcc')))
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->reject(fn (string $email): bool => $already->contains(mb_strtolower($email)));

        foreach ($copies as $email) {
            $message->addBcc($email);
        }
    }
}
