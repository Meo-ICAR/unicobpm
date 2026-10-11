<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Identificativo che accompagna ogni chiamata verso le altre app (X-Correlation-Id): quello della richiesta in corso, se c'è,
 * altrimenti uno per tutta l'esecuzione (comando, coda).
 */
class Correlation
{
    private ?string $id = null;

    public function id(): string
    {
        $incoming = request()?->header('X-Correlation-Id');

        return $this->id ??= (is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid());
    }

    public function use(string $id): void
    {
        $this->id = $id;
    }
}
