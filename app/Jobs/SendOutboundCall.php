<?php

namespace App\Jobs;

use App\Events\OutboundCallSucceeded;
use App\Models\OutboundCall;
use App\Models\WebhookEndpoint;
use App\Services\Correlation;
use App\Services\ExternalAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Un tentativo di consegna di una chiamata in uscita. Errori di rete e risposte 5xx/408/425/429 si ritentano (attese 1, 5, 15 minuti,
 * 1 e 4 ore); gli altri errori 4xx sono definitivi. Nei log e in `last_error` solo codici e tipo di errore, mai il contenuto.
 */
class SendOutboundCall implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 6;

    public function __construct(public int $callId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 14400];
    }

    public function handle(ExternalAppClient $client, Correlation $correlation): void
    {
        $call = OutboundCall::query()->find($this->callId);

        if ($call === null || $call->status !== OutboundCall::PENDING) {
            return;
        }

        if ($call->correlation_id) {
            $correlation->use($call->correlation_id);
        }

        $call->increment('attempts');

        try {
            $response = $call->isWebhook() ? $this->sendWebhook($call, $client) : $this->sendToApp($call, $client);
        } catch (Throwable $e) {
            $this->scheduleRetry($call, null, class_basename($e));

            throw new RuntimeException("Chiamata in uscita #{$call->id}: ".class_basename($e));
        }

        if ($response->successful()) {
            $call->update(['status' => OutboundCall::SENT, 'sent_at' => now(), 'last_status_code' => $response->status(), 'last_error' => null, 'next_attempt_at' => null]);
            OutboundCallSucceeded::dispatch($call);

            return;
        }

        if ($response->clientError() && ! in_array($response->status(), [408, 425, 429], true)) {
            $call->update(['status' => OutboundCall::FAILED, 'last_status_code' => $response->status(), 'last_error' => 'HTTP '.$response->status().' (definitivo)', 'next_attempt_at' => null]);
            Log::warning("Chiamata in uscita #{$call->id} rifiutata.", ['event' => $call->event, 'status' => $response->status()]);

            return;
        }

        $this->scheduleRetry($call, $response->status(), 'HTTP '.$response->status());

        throw new RuntimeException("Chiamata in uscita #{$call->id}: HTTP {$response->status()}");
    }

    public function failed(Throwable $exception): void
    {
        OutboundCall::query()->whereKey($this->callId)->where('status', OutboundCall::PENDING)->update(['status' => OutboundCall::FAILED, 'next_attempt_at' => null]);
        Log::error("Chiamata in uscita #{$this->callId} non consegnata dopo {$this->tries} tentativi.");
    }

    private function sendToApp(OutboundCall $call, ExternalAppClient $client): Response
    {
        return $client->for((string) $call->app)
            ->withHeaders(['Idempotency-Key' => $call->idempotency_key])
            ->send($call->method, $client->url((string) $call->app, $call->path), $call->payload === null ? [] : ['json' => $call->payload]);
    }

    private function sendWebhook(OutboundCall $call, ExternalAppClient $client): Response
    {
        $endpoint = WebhookEndpoint::query()->find($call->meta['webhook_endpoint_id'] ?? 0);

        if ($endpoint === null || ! $endpoint->is_active) {
            throw new RuntimeException('Webhook non più attivo.');
        }

        $body = json_encode($call->payload);

        return $client->base(10, withKey: false)
            ->withHeaders([
                'X-Unico-Event' => (string) ($call->meta['event'] ?? $call->event),
                'X-Unico-Delivery' => (string) $call->id,
                'X-Unico-Signature' => 'sha256='.hash_hmac('sha256', (string) $body, $endpoint->secret),
            ])
            ->withBody((string) $body, 'application/json')
            ->post($call->path);
    }

    private function scheduleRetry(OutboundCall $call, ?int $status, string $error): void
    {
        $delay = $this->backoff()[min($call->attempts, count($this->backoff())) - 1] ?? 14400;
        $call->update(['last_status_code' => $status, 'last_error' => $error, 'next_attempt_at' => now()->addSeconds($delay)]);
    }
}
