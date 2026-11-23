<?php

namespace App\Services;

use Unico\Core\Models\Fornitore;
use Unico\Core\Models\IncomingEmail;

/**
 * Il candidato alla rete commerciale è un produttore (`fornitoris`) non ancora operativo: `is_active` = false e data di decorrenza
 * (`stipulated_at`) vuota. Dalla email di candidatura letta da unicopec si ritrova il produttore con quell'indirizzo o se ne crea uno nuovo.
 * Diventa produttore operativo quando ha decorrenza e `is_active` = true.
 */
class CandidateResolver
{
    /** @return Fornitore|null il candidato; null se l'indirizzo è già di un produttore attivo o con decorrenza (non è una candidatura) */
    public function forEmail(IncomingEmail $email): ?Fornitore
    {
        $address = mb_strtolower(trim((string) $email->from_email));

        if ($address === '') {
            return null;
        }

        $existing = Fornitore::query()
            ->where(fn ($q) => $q->whereRaw('lower(email) = ?', [$address])->orWhereRaw('lower(email_private) = ?', [$address])->orWhereRaw('lower(pec) = ?', [$address]))
            ->first();

        if ($existing !== null) {
            return self::isCandidate($existing) ? $existing : null;
        }

        return Fornitore::query()->create([
            'name' => mb_strtoupper(trim((string) ($email->from_name ?: $address))),
            'email_private' => $address,
            'is_active' => false,
            'company_id' => $email->company_id,
        ]);
    }

    public static function isCandidate(Fornitore $fornitore): bool
    {
        return ! $fornitore->is_active && $fornitore->stipulated_at === null;
    }
}
