<?php

namespace Tests\Feature;

use App\Models\Process;
use App\Services\EmailSendingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckStaleExternalRecordsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stale_record_reminders' => [[
            'app' => 'unicocoge',
            'model' => 'sales_invoice',
            'label' => 'Fatture di vendita',
            'max_age_days' => 30,
            'recipient' => 'rino.muscetti@races.it',
            'process_code' => 'PRC-TEST-STALE',
            'date_field' => 'registration_date',
        ]]]);

        Process::create(['code' => 'PRC-TEST-STALE', 'name' => 'Test record vecchi', 'is_active' => true]);
    }

    private function fakeLatestCreatedAt(string $registeredAt): void
    {
        Http::fake(['*/api/models/sales_invoice/latest*' => Http::response(['fields' => ['registration_date' => $registeredAt, 'created_at' => now()->toIso8601String()]])]);
    }

    public function test_sends_reminder_when_latest_record_is_older_than_threshold(): void
    {
        $this->fakeLatestCreatedAt(now()->subDays(31)->toIso8601String());
        $this->mock(EmailSendingService::class)
            ->shouldReceive('send')->once()->with('rino.muscetti@races.it', \Mockery::type('string'), \Mockery::type('string'));

        $this->artisan('bpm:check-stale-records')->assertSuccessful();

    }

    public function test_the_age_is_taken_from_the_configured_date_field_and_requested_with_order_by(): void
    {
        $this->fakeLatestCreatedAt(now()->subDays(10)->toIso8601String());
        $this->mock(EmailSendingService::class)->shouldNotReceive('send');

        $this->artisan('bpm:check-stale-records')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'order_by=registration_date'));
    }

    public function test_does_not_send_reminder_when_its_process_is_not_active(): void
    {
        Process::where('code', 'PRC-TEST-STALE')->update(['is_active' => false]);
        $this->fakeLatestCreatedAt(now()->subDays(31)->toIso8601String());
        $this->mock(EmailSendingService::class)->shouldNotReceive('send');

        $this->artisan('bpm:check-stale-records')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_does_not_send_reminder_when_latest_record_is_recent(): void
    {
        $this->fakeLatestCreatedAt(now()->subDays(10)->toIso8601String());

        $this->mock(EmailSendingService::class)->shouldNotReceive('send');

        $this->artisan('bpm:check-stale-records')->assertSuccessful();
    }

    public function test_does_not_send_reminder_when_api_fails(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->mock(EmailSendingService::class)->shouldNotReceive('send');

        $this->artisan('bpm:check-stale-records')->assertSuccessful();
    }
}
