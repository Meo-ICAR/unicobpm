<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * L'amministratore a cui vanno le email dei processi quando nessuno dei destinatari previsti ha un utente o un'email
 * (`services.fallback_recipient`, env BPM_FALLBACK_EMAIL).
 */
class FallbackRecipient
{
    /** @return Collection<int, string> vuota se l'indirizzo non è configurato o non è valido */
    public static function emails(): Collection
    {
        $email = config('services.fallback_recipient');

        return collect(filter_var($email, FILTER_VALIDATE_EMAIL) ? [$email] : []);
    }
}
