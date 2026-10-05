<?php

namespace Tests\Feature;

use App\Jobs\MandatoryDeadlineWatchdogJob;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskExecution;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MandatoryDeadlineWatchdogJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_logs_a_reminder_when_a_step_exceeds_its_mandatory_days(): void
    {
        $process = Process::create(['name' => 'Test', 'code' => 'PRC-TEST-STEP-DEADLINE', 'is_active' => true]);
        $task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Step Test', 'ordine' => 10]);
        $instance = ProcessInstance::create(['process_id' => $process->id, 'status' => 'in_progress', 'current_task_id' => $task->id]);

        ProcessTaskExecution::create([
            'process_instance_id' => $instance->id,
            'process_task_id' => $task->id,
            'started_at' => now()->subDays(5),
            'mandatory_days_to_complete' => 3,
            'execution_status' => 'in_progress',
        ]);

        (new MandatoryDeadlineWatchdogJob)->handle();

        $this->assertTrue(
            Activity::where('event', 'mandatory_step_deadline_reached')->where('subject_id', $instance->id)->exists()
        );
    }

    public function test_does_not_log_a_reminder_when_the_step_is_still_on_time(): void
    {
        $process = Process::create(['name' => 'Test', 'code' => 'PRC-TEST-STEP-ONTIME', 'is_active' => true]);
        $task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Step Test', 'ordine' => 10]);
        $instance = ProcessInstance::create(['process_id' => $process->id, 'status' => 'in_progress', 'current_task_id' => $task->id]);

        ProcessTaskExecution::create([
            'process_instance_id' => $instance->id,
            'process_task_id' => $task->id,
            'started_at' => now()->subDay(),
            'mandatory_days_to_complete' => 3,
            'execution_status' => 'in_progress',
        ]);

        (new MandatoryDeadlineWatchdogJob)->handle();

        $this->assertFalse(
            Activity::where('event', 'mandatory_step_deadline_reached')->where('subject_id', $instance->id)->exists()
        );
    }

    public function test_logs_a_reminder_when_an_instance_exceeds_its_hard_deadline(): void
    {
        $process = Process::create(['name' => 'Test', 'code' => 'PRC-TEST-INSTANCE-DEADLINE', 'is_active' => true]);
        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'status' => 'in_progress',
            'hard_deadline_at' => now()->subDay(),
        ]);

        (new MandatoryDeadlineWatchdogJob)->handle();

        $this->assertTrue(
            Activity::where('event', 'mandatory_instance_deadline_reached')->where('subject_id', $instance->id)->exists()
        );
    }

    public function test_includes_the_eligible_count_in_the_reminder_message_when_the_process_flag_is_on(): void
    {
        $process = Process::create([
            'name' => 'Test',
            'code' => 'PRC-TEST-COUNT-IN-REMINDER',
            'is_active' => true,
            'include_eligible_count_in_reminders' => true,
            // Nessun target_model: eligibleRecordsCount() ritorna null, quindi il
            // messaggio non deve includere alcun conteggio (nessun errore).
        ]);
        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'status' => 'in_progress',
            'hard_deadline_at' => now()->subDay(),
        ]);

        (new MandatoryDeadlineWatchdogJob)->handle();

        $activity = Activity::where('event', 'mandatory_instance_deadline_reached')->where('subject_id', $instance->id)->first();

        $this->assertNotNull($activity);
        $this->assertStringNotContainsString('Record attualmente eleggibili', $activity->description);
    }
}
