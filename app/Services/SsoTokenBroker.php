<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Emette e verifica i token one-time usati per il passaggio con login
 * automatico da UnicoBPM verso le altre app (bpm-landing su UnicoLoan/
 * UnicoOAM/Proforma), riusando lo stesso schema già in produzione su quelle
 * app (POST {app}/api/verify-token con {token, email}).
 */
class SsoTokenBroker
{
    private const TTL_MINUTES = 5;

    private const CACHE_PREFIX = 'sso-token:';

    public function issueToken(string $email): string
    {
        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, $email, now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * Verifica il token e lo invalida (uso singolo), indipendentemente
     * dall'esito, per evitare replay.
     */
    public function verifyAndConsume(string $token, string $email): bool
    {
        $key = self::CACHE_PREFIX.$token;
        $storedEmail = Cache::get($key);

        Cache::forget($key);

        return $storedEmail !== null && hash_equals($storedEmail, $email);
    }
}
