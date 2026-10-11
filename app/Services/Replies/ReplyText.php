<?php

namespace App\Services\Replies;

/** Toglie da una risposta email il messaggio citato e la firma, lasciando solo ciò che ha scritto chi risponde. */
final class ReplyText
{
    public static function clean(?string $body, int $max = 2000): string
    {
        $lines = preg_split('/\R/u', (string) $body) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            // Da qui in poi è il messaggio originale.
            if (preg_match('/^\s*(-{2,}\s*(original message|messaggio originale|forwarded)|_{5,}|(il giorno|on)\s.+(ha scritto|wrote):?\s*$|(da|from):\s.+@|-----)/iu', $line)) {
                break;
            }

            if (preg_match('/^\s*>/', $line)) {
                continue;
            }

            // Firma standard («-- »): il resto non è risposta.
            if (preg_match('/^--\s*$/', $line)) {
                break;
            }

            $kept[] = $line;
        }

        return mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept))), 0, $max);
    }
}
