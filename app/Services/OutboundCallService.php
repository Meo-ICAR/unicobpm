<?php

namespace App\Services;

use App\Jobs\SendOutboundCall;
use App\Models\OutboundCall;

/**
 * Mette in coda una chiamata verso un'altra app (o un webhook): non si perde se l'app non risponde, viene ritentata con attesa
 * crescente e ha uno stato leggibile (Chiamate in uscita). Con la stessa chiave non si accoda due volte.
 */
class OutboundCallService
{
    public function __construct(private Correlation $correlation) {}

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $meta
     */
    public function enqueue(string $event, ?string $app, string $method, string $path, ?array $payload = null, array $meta = [], ?string $key = null): OutboundCall
    {
        $key ??= sha1(implode('|', [$event, $app, $method, $path, json_encode($payload)]));

        $call = OutboundCall::query()->firstOrCreate(['idempotency_key' => $key], [
            'event' => $event, 'app' => $app, 'method' => strtoupper($method), 'path' => $path, 'payload' => $payload, 'meta' => $meta,
            'correlation_id' => $this->correlation->id(), 'status' => OutboundCall::PENDING,
        ]);

        if ($call->wasRecentlyCreated) {
            SendOutboundCall::dispatch($call->id);
        }

        return $call;
    }

    /** Rimette in coda una chiamata fallita (azione «Riprova»). */
    public function retry(OutboundCall $call): void
    {
        $call->update(['status' => OutboundCall::PENDING, 'attempts' => 0, 'last_error' => null, 'next_attempt_at' => null]);
        SendOutboundCall::dispatch($call->id);
    }
}
