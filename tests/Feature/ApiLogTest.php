<?php

namespace Tests\Feature;

use App\Filament\Resources\ApiLogs\Pages\ListApiLogs;
use App\Filament\Resources\ApiLogs\Pages\ViewApiLog;
use App\Models\ApiLog;
use App\Models\User;
use App\Services\ExternalAppClient;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ApiLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['unico-core.bridge.api_key' => 'chiave-eventi']);
    }

    public function test_an_incoming_event_is_traced_with_request_response_and_ai_cost_without_the_secret_key(): void
    {
        $this->postJson('/api/events', [
            'event_id' => 'pratica.perfezionata:FIN-1', 'event' => 'pratica.perfezionata', 'source' => 'unicoagent', 'subject_ref' => 'FIN-1',
            'data' => ['ai_usage' => ['model' => 'claude-opus-5-5', 'input_tokens' => 1200, 'output_tokens' => 300, 'cost' => 0.0108]],
        ], ['X-Api-Key' => 'chiave-eventi'])->assertStatus(202);

        $log = ApiLog::query()->firstOrFail();

        $this->assertSame(ApiLog::IN, $log->direction);
        $this->assertSame('unicoagent', $log->app);
        $this->assertSame(202, $log->status_code);
        $this->assertSame('pratica.perfezionata:FIN-1', $log->event_id);
        $this->assertSame(1200, $log->ai_input_tokens);
        $this->assertSame('0.010800', $log->ai_cost);
        $this->assertSame('***', $log->request_headers['x-api-key']);
        $this->assertStringContainsString('"status":"ignored"', $log->response_body);
        $this->assertStringContainsString('registrato', $log->timeline()->pluck(1)->implode("\n"));
    }

    public function test_a_rejected_incoming_call_is_traced_too(): void
    {
        $this->postJson('/api/events', ['event' => 'x'], ['X-Api-Key' => 'sbagliata'])->assertStatus(401);

        $this->assertSame(401, ApiLog::query()->firstOrFail()->status_code);
    }

    public function test_outgoing_calls_are_traced_with_the_response_and_network_errors(): void
    {
        Http::fake(['*loan.test/ok*' => Http::response(['ok' => true], 201), '*loan.test/ko*' => fn () => throw new ConnectionException('boom')]);

        $client = app(ExternalAppClient::class);
        $client->base()->post('https://loan.test/ok?Passkey=segreta', ['a' => 1]);
        try {
            $client->base()->post('https://loan.test/ko');
        } catch (\Throwable) {
        }

        [$ok, $ko] = ApiLog::query()->orderBy('id')->get()->all();

        $this->assertSame(ApiLog::OUT, $ok->direction);
        $this->assertSame(201, $ok->status_code);
        $this->assertSame('https://loan.test/ok', $ok->url);
        $this->assertSame('{"a":1}', $ok->request_body);
        $this->assertSame('***', $ok->request_headers['X-Api-Key']);
        $this->assertNotNull($ko->error);
        $this->assertNull($ko->status_code);
    }

    public function test_other_apps_report_their_calls_with_ai_usage_and_the_call_itself_is_not_traced(): void
    {
        $this->postJson('/api/api-logs', [
            'origin' => 'unicoagent', 'method' => 'post', 'url' => 'https://api.anthropic.com/v1/messages', 'status_code' => 200, 'duration_ms' => 840,
            'ai' => ['model' => 'claude-opus-5-5', 'input_tokens' => 900, 'output_tokens' => 120, 'cost' => 0.006],
        ], ['X-Api-Key' => 'chiave-eventi'])->assertCreated();

        $log = ApiLog::query()->sole();

        $this->assertSame('unicoagent', $log->origin);
        $this->assertSame('api.anthropic.com', $log->app);
        $this->assertSame('POST', $log->method);
        $this->assertSame('0.006000', $log->ai_cost);

        $this->postJson('/api/api-logs', ['origin' => 'x'], ['X-Api-Key' => 'chiave-eventi'])->assertUnprocessable();
        $this->postJson('/api/api-logs', [], [])->assertUnauthorized();
        $this->assertSame(1, ApiLog::query()->count());
    }

    public function test_the_resource_lists_and_shows_the_trace(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $log = ApiLog::create(['direction' => 'in', 'method' => 'POST', 'url' => 'https://bpm.test/api/events', 'status_code' => 202, 'request_body' => '{"x":1}', 'response_body' => '{"status":"processed"}', 'ai_cost' => 0.0108]);

        Livewire::test(ListApiLogs::class)->assertCanSeeTableRecords([$log])->assertSee('$ 0,0108');
        Livewire::test(ViewApiLog::class, ['record' => $log->getRouteKey()])->assertSee('Costo interrogazione AI')->assertSee('processed');
    }
}
