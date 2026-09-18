<?php

namespace Tests\Feature;

use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifica che le tre azioni automatiche del "bot di sistema"
 * (ProcessTaskExecutionObserver) che non dipendono da Document/DocumentType/
 * EmailTemplate (che vivono sul DB esterno condiviso, mai toccato dai test)
 * marchino la risposta generata come operatore procedurale.
 */
class ProcessTaskExecutionOperatorTrackingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_validation_rule_success_is_recorded_as_a_procedural_operator(): void
    {
        $task = $this->makeTask();

        ProcessTaskItem::create([
            'process_task_id' => $task->id,
            'name' => 'Verifica stato',
            'action_type' => 'validation_rule',
            'is_required' => true,
            'config' => ['field_to_check' => 'status', 'min_length' => 3],
        ]);

        $instance = ProcessInstance::create(['process_id' => $task->process_id]);

        $answer = ProcessTaskItemAnswer::where('process_instance_id', $instance->id)->firstOrFail();

        $this->assertSame('procedural', $answer->operator_type);
        $this->assertSame('validation_rule', $answer->operator_label);
    }

    public function test_system_task_success_is_recorded_as_a_procedural_operator(): void
    {
        RecordingTestJob::$ran = false;

        $task = $this->makeTask();

        ProcessTaskItem::create([
            'process_task_id' => $task->id,
            'name' => 'Job di sistema',
            'action_type' => 'system_task',
            'is_required' => true,
            'handler_job' => RecordingTestJob::class,
            'config' => [],
        ]);

        $instance = ProcessInstance::create(['process_id' => $task->process_id]);

        $answer = ProcessTaskItemAnswer::where('process_instance_id', $instance->id)->firstOrFail();

        $this->assertTrue(RecordingTestJob::$ran);
        $this->assertSame('procedural', $answer->operator_type);
        $this->assertSame('system_task', $answer->operator_label);
    }

    public function test_blacklist_check_success_is_recorded_as_a_procedural_operator(): void
    {
        Http::fake(['*' => Http::response(['agente_blacklistato' => false])]);

        $task = $this->makeTask();

        ProcessTaskItem::create([
            'process_task_id' => $task->id,
            'name' => 'Verifica blacklist',
            'action_type' => 'blacklist_check',
            'is_required' => true,
            'config' => ['pratica_id_field' => 'id'],
        ]);

        $instance = ProcessInstance::create(['process_id' => $task->process_id]);

        $answer = ProcessTaskItemAnswer::where('process_instance_id', $instance->id)->firstOrFail();

        $this->assertSame('procedural', $answer->operator_type);
        $this->assertSame('blacklist_check', $answer->operator_label);
    }

    private function makeTask(): ProcessTask
    {
        $process = Process::create(['name' => 'Test Operator Tracking', 'code' => 'PRC-OP-'.uniqid(), 'is_active' => true]);

        return ProcessTask::create([
            'process_id' => $process->id,
            'name' => 'Task Automatico',
            'ordine' => 1,
        ]);
    }
}

class RecordingTestJob
{
    public static bool $ran = false;

    public function __construct(protected int $processInstanceId, protected array $config = []) {}

    public function handle(): void
    {
        self::$ran = true;
    }
}
