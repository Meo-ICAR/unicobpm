<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Risolve l'URL base di uno degli applicativi esterni interrogabili/scrivibili
 * da UnicoBPM (oggi UnicoLoan e UnicoOAM, due deploy dello stesso codebase che
 * condividono gli stessi database). Il default 'unicoloan' viene applicato
 * dai chiamanti quando una configurazione non specifica esplicitamente l'app.
 */
class ExternalAppResolver
{
    public const DEFAULT_APP = 'unicoloan';

    public function urlFor(string $app): string
    {
        $url = config("services.apps.{$app}.url");

        if (! $url) {
            throw new InvalidArgumentException("Applicativo esterno sconosciuto: {$app}");
        }

        return rtrim($url, '/');
    }
}
