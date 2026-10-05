<?php

namespace Tests\Feature;

use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskExecution;
use App\Models\ProcessTaskItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlacklistCheckObserverTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * UnicoBPM has no local Pratica model — it asks UnicoLoan for the pratica's
     * blacklist status via API when a 'blacklist_check' task item is entered.
     */
    public function test_suspends_the_instance_when_unicoloan_reports_the_agent_as_blacklisted(): void
    {
        config()->set('services.apps.unicoloan.url', 'https://unicoloan.test');

        Http::fake([
            'https://unicoloan.test/api/pratiche/*' => Http::response(['agente_blacklistato' => true]),
        ]);

        [$instance, $task] = $this->makeInstanceWithBlacklistCheckTask();

        ProcessTaskExecution::create([
            'process_instance_id' => $instance->id,
            'process_task_id' => $task->id,
            'started_at' => now(),
            'execution_status' => 'in_progress',
        ]);

        $this->assertSame('suspended', $instance->fresh()->status);
    }

    public function test_does_not_suspend_the_instance_when_the_agent_is_not_blacklisted(): void
    {
        config()->set('services.apps.unicoloan.url', 'https://unicoloan.test');

        Http::fake([
            'https://unicoloan.test/api/pratiche/*' => Http::response(['agente_blacklistato' => false]),
        ]);

        [$instance, $task] = $this->makeInstanceWithBlacklistCheckTask();

        ProcessTaskExecution::create([
            'process_instance_id' => $instance->id,
            'process_task_id' => $task->id,
            'started_at' => now(),
            'execution_status' => 'in_progress',
        ]);

        $this->assertNotSame('suspended', $instance->fresh()->status);
    }

    /**
     * The target app is per-ProcessTaskItem config, not hardcoded: a
     * 'blacklist_check' item can point at unicooam instead of unicoloan.
     */
    public function test_queries_the_app_named_in_the_item_config_instead_of_the_default(): void
    {
        config()->set('services.apps.unicooam.url', 'https://unicooam.test');

        Http::fake([
            'https://unicooam.test/api/pratiche/*' => Http::response(['agente_blacklistato' => true]),
        ]);

        [$instance, $task] = $this->makeInstanceWithBlacklistCheckTask(['app' => 'unicooam']);

        ProcessTaskExecution::create([
            'process_instance_id' => $instance->id,
            'process_task_id' => $task->id,
            'started_at' => now(),
            'execution_status' => 'in_progress',
        ]);

        $this->assertSame('suspended', $instance->fresh()->status);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://unicooam.test/'));
    }

    /**
     * @param  array<string, mixed>  $extraConfig
     * @return array{0: ProcessInstance, 1: ProcessTask}
     */
    private function makeInstanceWithBlacklistCheckTask(array $extraConfig = []): array
    {
        $process = Process::create(['name' => 'Istruttoria', 'code' => 'PRC-ISTRUTTORIA', 'is_active' => true]);

        $task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Invio Pratica', 'ordine' => 20]);

        ProcessTaskItem::create([
            'process_task_id' => $task->id,
            'name' => 'Verifica Blacklist Agente',
            'ordine' => 1,
            'action_type' => 'blacklist_check',
            'is_required' => true,
            'config' => ['pratica_id_field' => 'subject_id', ...$extraConfig],
        ]);

        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'subject_type' => null,
            'subject_id' => 'pratica-uuid-123',
            'status' => 'in_progress',
            'current_task_id' => $task->id,
        ]);

        return [$instance, $task];
    }
}
