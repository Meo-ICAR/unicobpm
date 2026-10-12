<?php

namespace App\Services;

use App\Models\ApiLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Le chiamate verso le altre app partono tutte da qui: JSON, tempi massimi, chiave condivisa del bridge e identificativo di
 * correlazione, così una catena unicobpm → app si segue in tutti i log.
 */
class ExternalAppClient
{
    public function __construct(private ExternalAppResolver $apps, private Correlation $correlation, private ApiLogger $logger) {}

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
        $request = Http::asJson()->timeout($timeout)->connectTimeout(4)->withHeaders(['X-Correlation-Id' => $this->correlation->id()])
            ->withMiddleware($this->traceMiddleware());

        return $withKey ? $request->withHeaders(['X-Api-Key' => (string) config('services.bpm.bridge_api_key')]) : $request;
    }

    /** Guzzle middleware: ogni chiamata in uscita finisce in `api_logs`, con risposta (o tipo di errore di rete) e durata. */
    private function traceMiddleware(): callable
    {
        return fn (callable $handler): callable => function (RequestInterface $request, array $options) use ($handler) {
            $startedAt = microtime(true);
            $correlationId = $this->correlation->id();
            $log = fn (?ResponseInterface $response, ?string $error) => $this->logger->record([
                'direction' => ApiLog::OUT,
                'app' => $request->getUri()->getHost(),
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri()->withQuery(''),
                'request_headers' => $request->getHeaders(),
                'request_body' => (string) $request->getBody(),
                'status_code' => $response?->getStatusCode(),
                'response_headers' => $response?->getHeaders(),
                'response_body' => $response === null ? null : (string) $response->getBody(),
                'error' => $error,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'correlation_id' => $correlationId,
            ]);

            try {
                $promise = $handler($request, $options);
            } catch (Throwable $e) {
                $log(null, class_basename($e));

                throw $e;
            }

            return $promise->then(
                function (ResponseInterface $response) use ($log) {
                    $log($response, null);
                    $response->getBody()->rewind();

                    return $response;
                },
                function ($reason) use ($log) {
                    $log(null, $reason instanceof Throwable ? class_basename($reason) : 'errore');

                    throw $reason;
                },
            );
        };
    }
}
