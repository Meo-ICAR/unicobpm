<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use App\Services\ApiLogger;
use App\Services\Correlation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Traccia le chiamate in ingresso dalle altre app: richiesta, risposta, durata e costo AI dichiarato nei dati dell'evento. */
class LogInboundApiCall
{
    public function __construct(private ApiLogger $logger, private Correlation $correlation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        $this->logger->record([
            'direction' => ApiLog::IN,
            'company_id' => $this->logger->companyId((array) $request->input('data', [])),
            'app' => $request->input('source'),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'request_headers' => $request->headers->all(),
            'request_body' => $request->getContent(),
            'status_code' => $response->getStatusCode(),
            'response_body' => $response->getContent() ?: null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'correlation_id' => $this->correlation->id(),
            'event_id' => is_string($request->input('event_id')) ? mb_substr($request->input('event_id'), 0, 120) : null,
        ] + $this->logger->aiUsage((array) $request->input('data', [])));

        return $response;
    }
}
