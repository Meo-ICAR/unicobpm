<?php

namespace Tests\Feature;

use App\Services\EmailSendingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckStaleExternalRecordsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stale_record_reminders' => [[
            'app' => 'proforma',
            'model' => 'sales_invoice',
            'label' => 'Fatture di vendita',
            'max_age_days' => 30,
            'recipient' => 'rino.muscetti@races.it',
        ]]]);
    }

    private function fakeLatestCreatedAt(string $createdAt): void
    {
        Http::fake(['*/api/models/sales_invoice/latest' => Http::response(['fields' => ['created_at' => $createdAt]])]);
    }

    public function test_sends_reminder_when_latest_record_is_older_than_threshold(): void
    {
        $this->fakeLatestCreatedAt(now()->subDays(31)->toIso8601String());
        $this->mock(EmailSendingService::class)
            ->shouldReceive('send')->once()->with('rino.muscetti@races.it', \Mockery::type('string'), \Mockery::type('string'));

        $this->artisan('bpm:check-stale-records')->assertSuccessful();

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
