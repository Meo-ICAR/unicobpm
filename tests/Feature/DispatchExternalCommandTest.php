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

        $this->artisan('bpm:dispatch-external-command', ['app' => 'proforma', 'external_command' => 'vcoge:calculate', '--option' => ['invia']])
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/commands/vcoge:calculate')
            && $request->header('X-Api-Key') === ['secret']
            && $request['options'] === ['invia' => true]);
    }

    public function test_fails_when_the_external_app_rejects_the_command(): void
    {
        Http::fake(['*' => Http::response(['message' => 'no'], 404)]);

        $this->artisan('bpm:dispatch-external-command', ['app' => 'proforma', 'external_command' => 'x:y'])
            ->assertFailed();
    }
}
