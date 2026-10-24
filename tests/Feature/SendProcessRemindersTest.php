<?php

namespace Tests\Feature;

use App\Models\Fornitore;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use Database\Seeders\ProcessEmailTemplateSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendProcessRemindersTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ProcessInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        $this->seed(ProcessEmailTemplateSeeder::class);

        $process = Process::create(['name' => 'Istruttoria', 'code' => 'PRC-REM-'.uniqid(), 'is_active' => true]);
        $task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Raccolta documentazione', 'ordine' => 1, 'has_reminders' => true, 'reminder_interval_days' => 3, 'max_reminders' => 2]);
        ProcessTaskItem::create(['process_task_id' => $task->id, 'name' => 'Busta paga', 'action_type' => 'document_upload', 'is_required' => true]);
        $agent = Fornitore::query()->create(['name' => 'Rossi', 'email' => 'rossi@example.com']);
        $this->instance = ProcessInstance::create(['process_id' => $process->id, 'current_task_id' => $task->id, 'status' => 'in_progress', 'title' => 'Pratica Rossi', 'subject_type' => 'fornitore', 'subject_id' => $agent->id]);
    }

    /** @return list<string> */
    private function sent(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage()->getSubject())->values()->all();
    }

    public function test_reminders_start_after_the_interval_from_the_step_and_stop_at_the_maximum(): void
    {
        $this->artisan('bpm:send-reminders')->assertSuccessful();
        $this->assertSame([], $this->sent(), 'Prima dell\'intervallo non parte nulla.');

        $this->travel(4)->days();
        $this->artisan('bpm:send-reminders')->assertSuccessful();
        $this->assertSame(['Sollecito n. 1: documenti mancanti per Pratica Rossi'], $this->sent());
        $body = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString('- Busta paga', $body);

        $this->travel(1)->days();
        $this->artisan('bpm:send-reminders');
        $this->assertCount(1, $this->sent(), 'Il secondo aspetta altri 3 giorni.');

        $this->travel(3)->days();
        $this->artisan('bpm:send-reminders');
        $this->travel(4)->days();
        $this->artisan('bpm:send-reminders');
        $this->assertCount(2, $this->sent(), 'Non oltre il massimo del passo.');
        $this->assertSame(2, $this->instance->fresh()->reminders_sent_count);
    }
}
