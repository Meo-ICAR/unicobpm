<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Le chiamate verso le altre app partono tutte da qui: JSON, tempi massimi, chiave condivisa del bridge e identificativo di
 * correlazione, così una catena unicobpm → app si segue in tutti i log.
 */
class ExternalAppClient
{
    public function __construct(private ExternalAppResolver $apps, private Correlation $correlation) {}

    public function url(string $app, string $path): string
    {
        return $this->apps->urlFor($app).'/'.ltrim($path, '/');
    }

    public function for(string $app, int $timeout = 15): PendingRequest
    {
        return $this->base($timeout);
    }

    /** Richiesta già configurata (senza indirizzo): per i webhook di terzi, che non ricevono la chiave del bridge. */
    public function base(int $timeout = 15, bool $withKey = true): PendingRequest
    {
        $request = Http::asJson()->timeout($timeout)->connectTimeout(4)->withHeaders(['X-Correlation-Id' => $this->correlation->id()]);

        return $withKey ? $request->withHeaders(['X-Api-Key' => (string) config('services.bpm.bridge_api_key')]) : $request;
    }
}
