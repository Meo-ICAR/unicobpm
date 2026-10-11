<?php

namespace App\Services;

use App\Models\WebhookEndpoint;

/**
 * Avvisa i terzi iscritti quando succede qualcosa nei processi: una chiamata in coda per ogni destinatario, firmata con il suo segreto.
 * Corpo: {"event", "occurred_at", "data": {...}}; intestazioni X-Unico-Event, X-Unico-Delivery, X-Unico-Signature (sha256=HMAC del corpo).
 */
class WebhookDispatcher
{
    public const PROCESS_STATUS_CHANGED = 'process.status_changed';

    public function __construct(private OutboundCallService $calls) {}

    /** @param array<string, mixed> $data */
    public function dispatch(string $event, array $data, string $uniqueRef): int
    {
        $sent = 0;

        foreach (WebhookEndpoint::query()->where('is_active', true)->get() as $endpoint) {
            if (! $endpoint->listensTo($event) || (! str_starts_with($endpoint->url, 'https://') && ! app()->environment(['local', 'testing']))) {
                continue;
            }

            $this->calls->enqueue(
                "webhook:{$event}", null, 'POST', $endpoint->url,
                ['event' => $event, 'occurred_at' => now()->toIso8601String(), 'data' => $data],
                ['webhook_endpoint_id' => $endpoint->id, 'event' => $event],
                sha1("webhook|{$event}|{$endpoint->id}|{$uniqueRef}"),
            );
            $sent++;
        }

        return $sent;
    }
}
