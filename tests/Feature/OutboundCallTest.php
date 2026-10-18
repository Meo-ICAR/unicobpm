<?php

namespace Tests\Feature;

use App\Jobs\SendOutboundCall;
use App\Models\Fornitore;
use App\Models\OutboundCall;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\WebhookEndpoint;
use App\Services\Correlation;
use App\Services\ExternalAppClient;
use App\Services\OutboundCallService;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OutboundCallTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.apps.unicofinance.url' => 'https://unicofinance.test', 'services.bpm.bridge_api_key' => 'chiave-bridge']);
    }

    private function enqueue(string $key = 'k1'): OutboundCall
    {
        return app(OutboundCallService::class)->enqueue('completion_write', 'unicofinance', 'PATCH', '/api/models/fornitore/abc', ['field' => 'stipulated_at', 'value' => '2026-10-11'], ['instance_id' => 0, 'field' => 'stipulated_at', 'app' => 'unicofinance'], $key);
    }

    public function test_a_delivered_call_is_marked_sent_with_the_shared_headers(): void
    {
        Http::fake(['https://unicofinance.test/*' => Http::response(['ok' => true])]);
        app(Correlation::class)->use('corr-12345678');

        $call = $this->enqueue()->fresh();

        $this->assertSame('sent', $call->status);
        $this->assertSame(1, $call->attempts);
        $this->assertSame(200, $call->last_status_code);
        $this->assertNotNull($call->sent_at);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request->url() === 'https://unicofinance.test/api/models/fornitore/abc'
            && $request->header('X-Api-Key') === ['chiave-bridge'] && $request->header('X-Correlation-Id') === ['corr-12345678']
            && $request->header('Idempotency-Key') === ['k1'] && $request['field'] === 'stipulated_at');
    }

    public function test_the_same_key_is_not_queued_twice(): void
    {
        Http::fake(['*' => Http::response([])]);

        $this->enqueue('uguale');
        $this->enqueue('uguale');

        $this->assertSame(1, OutboundCall::count());
        Http::assertSentCount(1);
    }

    public function test_a_server_error_keeps_the_call_pending_and_schedules_the_retry(): void
    {
        Http::fake(['*' => Http::response('boom', 503)]);
        Queue::fake();
        $call = $this->enqueue();

        try {
            (new SendOutboundCall($call->id))->handle(app(ExternalAppClient::class), app(Correlation::class));
            $this->fail('Doveva lanciare per ritentare.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 503', $e->getMessage());
        }

        $call->refresh();
        $this->assertSame('pending', $call->status);
        $this->assertSame(503, $call->last_status_code);
        $this->assertSame('HTTP 503', $call->last_error);
        $this->assertNotNull($call->next_attempt_at);
        $this->assertStringNotContainsString('boom', (string) $call->last_error);
    }

    public function test_a_network_error_is_retried_and_exhausted_attempts_fail_the_call(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        Queue::fake();
        $call = $this->enqueue();

        try {
            (new SendOutboundCall($call->id))->handle(app(ExternalAppClient::class), app(Correlation::class));
        } catch (\RuntimeException) {
        }

        $this->assertSame('pending', $call->fresh()->status);
        $this->assertSame('ConnectionException', $call->fresh()->last_error);

        (new SendOutboundCall($call->id))->failed(new \RuntimeException('fine tentativi'));
        $this->assertSame('failed', $call->fresh()->status);
    }

    public function test_a_client_error_is_final_but_a_429_is_retried(): void
    {
        Queue::fake();
        Http::fake(fn ($request) => $request->header('Idempotency-Key') === ['k-429'] ? Http::response([], 429) : Http::response([], 422));
        $final = $this->enqueue('k-422');
        (new SendOutboundCall($final->id))->handle(app(ExternalAppClient::class), app(Correlation::class));
        $this->assertSame('failed', $final->fresh()->status);
        $this->assertSame('HTTP 422 (definitivo)', $final->fresh()->last_error);

        $limited = $this->enqueue('k-429');
        try {
            (new SendOutboundCall($limited->id))->handle(app(ExternalAppClient::class), app(Correlation::class));
        } catch (\RuntimeException) {
        }
        $this->assertSame('pending', $limited->fresh()->status);
    }

    public function test_retry_puts_a_failed_call_back_in_the_queue(): void
    {
        Queue::fake();
        $call = OutboundCall::create(['event' => 'x', 'app' => 'unicofinance', 'method' => 'PATCH', 'path' => '/x', 'idempotency_key' => 'r1', 'status' => 'failed', 'attempts' => 6, 'last_error' => 'HTTP 503']);

        app(OutboundCallService::class)->retry($call);

        $this->assertSame('pending', $call->fresh()->status);
        $this->assertSame(0, $call->fresh()->attempts);
        Queue::assertPushed(SendOutboundCall::class);
    }

    public function test_the_completion_write_goes_through_the_queue_and_is_logged_on_delivery(): void
    {
        Http::fake(['https://unicofinance.test/*' => Http::response(['ok' => true])]);
        $process = Process::create(['name' => 'Onboarding', 'code' => 'PRC-OUT', 'is_active' => true, 'completion_write_field' => 'stipulated_at', 'completion_write_value' => 'now']);
        $instance = ProcessInstance::create(['process_id' => $process->id, 'subject_type' => Fornitore::class, 'subject_id' => 'fornitore-uuid-1', 'status' => 'in_progress']);

        $instance->update(['status' => 'completed', 'completed_at' => now()]);

        $call = OutboundCall::where('event', 'completion_write')->sole();
        $this->assertSame('sent', $call->status);
        $this->assertSame("completion:{$instance->id}:stipulated_at", $call->idempotency_key);
        $this->assertSame(1, Activity::where('event', 'completion_field_written')->count());
    }

    public function test_an_unreachable_app_does_not_lose_the_completion_write(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));
        Queue::fake();
        $process = Process::create(['name' => 'Onboarding', 'code' => 'PRC-OUT2', 'is_active' => true, 'completion_write_field' => 'stipulated_at', 'completion_write_value' => 'now']);
        $instance = ProcessInstance::create(['process_id' => $process->id, 'subject_type' => Fornitore::class, 'subject_id' => 'fornitore-uuid-2', 'status' => 'in_progress']);

        $instance->update(['status' => 'completed', 'completed_at' => now()]);

        $this->assertSame('pending', OutboundCall::where('event', 'completion_write')->sole()->status);
        Queue::assertPushed(SendOutboundCall::class);
        $this->assertSame(0, Activity::where('event', 'completion_field_written')->count());
    }

    // --- Webhook ---

    private function endpoint(array $attributes = []): WebhookEndpoint
    {
        return WebhookEndpoint::create($attributes + ['name' => 'Terzo', 'url' => 'https://terzo.test/hook', 'secret' => 'segreto-terzo', 'events' => [], 'is_active' => true]);
    }

    public function test_a_status_change_is_sent_to_the_subscribed_webhooks_with_a_signature(): void
    {
        Http::fake(['https://terzo.test/*' => Http::response([], 200)]);
        $this->endpoint();
        $this->endpoint(['name' => 'Spento', 'url' => 'https://spento.test/hook', 'is_active' => false]);
        $this->endpoint(['name' => 'Altro evento', 'url' => 'https://altro.test/hook', 'events' => ['task.assigned']]);
        $process = Process::create(['name' => 'Onboarding', 'code' => 'PRC-WH', 'is_active' => true]);
        $instance = ProcessInstance::create(['process_id' => $process->id, 'subject_type' => Fornitore::class, 'subject_id' => 'f-1', 'status' => 'in_progress']);

        $instance->update(['status' => 'suspended']);

        $call = OutboundCall::where('event', 'webhook:process.status_changed')->sole();
        $this->assertSame('sent', $call->status);
        Http::assertSent(function ($request) {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 'segreto-terzo');

            return $request->url() === 'https://terzo.test/hook'
                && $request->header('X-Unico-Signature') === [$expected]
                && $request->header('X-Unico-Event') === ['process.status_changed']
                && $request->header('X-Api-Key') === []
                && $request['data']['status'] === 'suspended' && $request['data']['previous_status'] === 'in_progress' && $request['data']['process_code'] === 'PRC-WH';
        });
        Http::assertSentCount(1);
    }

    public function test_a_webhook_over_plain_http_is_ignored_outside_development(): void
    {
        Http::fake();
        $this->endpoint(['url' => 'http://terzo.test/hook']);
        $this->app['env'] = 'production';

        $count = app(WebhookDispatcher::class)->dispatch('process.status_changed', ['x' => 1], 'ref-1');

        $this->assertSame(0, $count);
        $this->assertSame(0, OutboundCall::count());
    }
}
