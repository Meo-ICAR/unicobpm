<?php

namespace Tests\Feature;

use App\Filament\Resources\OutboundCalls\Pages\ListOutboundCalls;
use App\Filament\Resources\WebhookEndpoints\Pages\CreateWebhookEndpoint;
use App\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use App\Jobs\SendOutboundCall;
use App\Models\OutboundCall;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OutboundCallResourcesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_failed_calls_can_be_retried_from_the_list(): void
    {
        Queue::fake();
        $failed = OutboundCall::create(['event' => 'completion_write', 'app' => 'unicofinance', 'method' => 'PATCH', 'path' => '/x', 'idempotency_key' => 'a', 'status' => 'failed', 'attempts' => 6, 'last_error' => 'HTTP 503']);
        $sent = OutboundCall::create(['event' => 'completion_write', 'app' => 'unicofinance', 'method' => 'PATCH', 'path' => '/y', 'idempotency_key' => 'b', 'status' => 'sent', 'attempts' => 1]);

        Livewire::test(ListOutboundCalls::class)
            ->assertCanSeeTableRecords([$failed, $sent])
            ->filterTable('status', 'failed')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$sent])
            ->callAction(TestAction::make('riprova')->table($failed))
            ->assertNotified('Chiamata rimessa in coda');

        $this->assertSame('pending', $failed->fresh()->status);
        Queue::assertPushed(SendOutboundCall::class);
    }

    public function test_a_webhook_endpoint_is_created_with_an_encrypted_secret(): void
    {
        Livewire::test(CreateWebhookEndpoint::class)
            ->fillForm(['name' => 'Terzo', 'url' => 'https://terzo.test/hook', 'secret' => 'segreto-lungo', 'events' => ['process.status_changed'], 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $endpoint = WebhookEndpoint::sole();
        $this->assertSame('segreto-lungo', $endpoint->secret);
        $this->assertNotSame('segreto-lungo', \DB::table('webhook_endpoints')->value('secret'));
        $this->assertTrue($endpoint->listensTo('process.status_changed'));
        Livewire::test(ListWebhookEndpoints::class)->assertCanSeeTableRecords([$endpoint]);
    }

    public function test_the_address_must_be_a_valid_url(): void
    {
        Livewire::test(CreateWebhookEndpoint::class)
            ->fillForm(['name' => 'Terzo', 'url' => 'non-un-indirizzo', 'secret' => 'segreto-lungo'])
            ->call('create')
            ->assertHasFormErrors(['url']);
    }
}
