<?php

namespace Tests\Feature;

use App\Models\Fornitore;
use App\Models\Process;
use App\Models\ProcessInstance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompletionWriteBackTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * When a ProcessInstance's status transitions to 'completed', and its
     * Process has `completion_write_field` configured (e.g. AGENT_ONBOARDING
     * writing 'stipulated_at'), ProcessInstanceObserver::updated() must ask
     * UnicoLoan's generic field-write API to write it — UnicoBPM never
     * updates the subject's own table directly.
     */
    public function test_asks_unicoloan_to_write_the_configured_field_when_the_instance_completes(): void
    {
        config()->set('services.apps.unicoloan.url', 'https://unicoloan.test');

        Http::fake([
            'https://unicoloan.test/api/models/*' => Http::response(['field' => 'stipulated_at', 'value_stored' => now()->toDateString()]),
        ]);

        $process = Process::create([
            'name' => 'Onboarding Test',
            'code' => 'PRC-TEST-ONB',
            'is_active' => true,
            'completion_write_field' => 'stipulated_at',
            'completion_write_value' => 'now',
        ]);

        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'subject_type' => Fornitore::class,
            'subject_id' => 'fornitore-uuid-123',
            'status' => 'in_progress',
        ]);

        $instance->update(['status' => 'completed', 'completed_at' => now()]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://unicoloan.test/api/models/fornitore/fornitore-uuid-123'
                && $request->method() === 'PATCH'
                && $request['field'] === 'stipulated_at';
        });
    }

    /**
     * The target app is per-Process config, not hardcoded: completion_write_app
     * can point at unicooam instead of the 'unicoloan' default.
     */
    public function test_writes_to_the_app_named_in_completion_write_app(): void
    {
        config()->set('services.apps.unicooam.url', 'https://unicooam.test');

        Http::fake([
            'https://unicooam.test/api/models/*' => Http::response(['field' => 'dismissed_at', 'value_stored' => now()->toDateString()]),
        ]);

        $process = Process::create([
            'name' => 'Offboarding Test',
            'code' => 'PRC-TEST-OFF',
            'is_active' => true,
            'completion_write_field' => 'dismissed_at',
            'completion_write_value' => 'now',
            'completion_write_app' => 'unicooam',
        ]);

        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'subject_type' => Fornitore::class,
            'subject_id' => 'fornitore-uuid-456',
            'status' => 'in_progress',
        ]);

        $instance->update(['status' => 'completed', 'completed_at' => now()]);

        Http::assertSent(fn ($request) => $request->url() === 'https://unicooam.test/api/models/fornitore/fornitore-uuid-456');
    }

    public function test_does_not_call_unicoloan_when_the_process_has_no_completion_write_field(): void
    {
        config()->set('services.apps.unicoloan.url', 'https://unicoloan.test');

        Http::fake();

        $process = Process::create([
            'name' => 'Processo Senza Write-Back',
            'code' => 'PRC-TEST-NOOP',
            'is_active' => true,
        ]);

        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'subject_type' => Fornitore::class,
            'subject_id' => 'fornitore-uuid-123',
            'status' => 'in_progress',
        ]);

        $instance->update(['status' => 'completed', 'completed_at' => now()]);

        Http::assertNothingSent();
    }
}
