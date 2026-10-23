<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispatchExternalCommandTest extends TestCase
{
    public function test_posts_the_command_to_the_external_app_with_the_api_key(): void
    {
        config(['services.bpm.bridge_api_key' => 'secret']);
        Http::fake(['*/api/commands/vcoge:calculate' => Http::response([], 202)]);

        $this->artisan('bpm:dispatch-external-command', ['app' => 'unicocoge', 'external_command' => 'vcoge:calculate', '--option' => ['invia']])
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/commands/vcoge:calculate')
            && $request->header('X-Api-Key') === ['secret']
            && $request['options'] === ['invia' => true]);
    }

    public function test_fails_when_the_external_app_rejects_the_command(): void
    {
        Http::fake(['*' => Http::response(['message' => 'no'], 404)]);

        $this->artisan('bpm:dispatch-external-command', ['app' => 'unicocoge', 'external_command' => 'x:y'])
            ->assertFailed();
    }

    public function test_wait_reads_the_outcome_of_the_run_and_succeeds_when_the_command_succeeded(): void
    {
        config(['services.bpm.bridge_api_key' => 'secret']);
        Http::fake([
            '*/api/commands/vcoge:calculate' => Http::response(['run_id' => 7], 202),
            '*/api/commands/runs/7' => Http::response(['status' => 'succeeded', 'finished' => true, 'exit_code' => 0, 'attempts' => 1]),
        ]);

        $this->artisan('bpm:dispatch-external-command', ['app' => 'unicocoge', 'external_command' => 'vcoge:calculate', '--wait' => true])
            ->expectsOutputToContain('lancio #7')
            ->expectsOutputToContain('Esito: succeeded')
            ->assertSuccessful();
    }

    public function test_wait_fails_when_the_command_failed(): void
    {
        config(['services.bpm.bridge_api_key' => 'secret']);
        Http::fake([
            '*/api/commands/x:y' => Http::response(['run_id' => 9], 202),
            '*/api/commands/runs/9' => Http::response(['status' => 'failed', 'finished' => true, 'exit_code' => 1, 'attempts' => 3]),
        ]);

        $this->artisan('bpm:dispatch-external-command', ['app' => 'unicocoge', 'external_command' => 'x:y', '--wait' => true])->assertFailed();
    }

    public function test_wait_gives_up_after_the_timeout(): void
    {
        Http::fake([
            '*/api/commands/x:y' => Http::response(['run_id' => 3], 202),
            '*/api/commands/runs/3' => Http::response(['status' => 'running', 'finished' => false]),
        ]);

        $this->artisan('bpm:dispatch-external-command', ['app' => 'unicocoge', 'external_command' => 'x:y', '--wait' => true, '--timeout' => 1])->assertFailed();
    }
}
