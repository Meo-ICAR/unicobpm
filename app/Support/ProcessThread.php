<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Identificativo di conversazione delle email dei processi: l'email parte con Message-ID `<bpm-{istanza}-{esecuzione}-{casuale}@dominio>`
 * e la risposta lo riporta in `In-Reply-To` / `References`, da cui si risale al processo senza dipendere dall'oggetto.
 */
final class ProcessThread
{
    /** Message-ID senza parentesi angolari, come lo vuole Symfony. */
    public static function messageId(int $instanceId, ?int $executionId): string
    {
        return 'bpm-'.$instanceId.'-'.($executionId ?? 0).'-'.Str::lower(Str::random(10)).'@'.self::domain();
    }

    /** @return array{instance: int, execution: ?int}|null */
    public static function parse(?string $headers): ?array
    {
        if ($headers !== null && preg_match('/<bpm-(\d+)-(\d+)-[a-z0-9]+@[^>\s]+>/i', $headers, $m)) {
            return ['instance' => (int) $m[1], 'execution' => (int) $m[2] ?: null];
        }

        return null;
    }

    private static function domain(): string
    {
        return (string) (config('services.bpm.thread_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }
}
