<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Risolve l'URL base di uno degli applicativi esterni interrogabili/scrivibili
 * da UnicoBPM (UnicoFinance per le pratiche, UnicoOAM, UnicoCoge...). Il default
 * 'unicofinance' viene applicato dai chiamanti quando una configurazione non
 * specifica esplicitamente l'app.
 */
class ExternalAppResolver
{
    public const DEFAULT_APP = 'unicofinance';

    public function urlFor(string $app): string
    {
        $url = config("services.apps.{$app}.url");

        if (! $url) {
            throw new InvalidArgumentException("Applicativo esterno sconosciuto: {$app}");
        }

        return rtrim($url, '/');
    }

    public function labelFor(string $app): string
    {
        return (string) (config("services.apps.{$app}.label") ?: $app);
    }

    /**
     * @return string[] Le chiavi di tutti gli applicativi esterni configurati.
     */
    public function allApps(): array
    {
        return array_keys((array) config('services.apps', []));
    }
}
