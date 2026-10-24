<?php

namespace Tests\Feature;

use App\Filament\Actions\StartProcessAction;
use App\Jobs\SendOutboundCall;
use App\Models\Employee;
use App\Models\OutboundCall;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use App\Services\EmailSendingService;
use App\Services\ExternalCheckRunner;
use App\Services\ExternalCommandRunner;
use Database\Seeders\ComplianceDocumentazioneProcessSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class MonthlyProvvigioniStepsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ComplianceDocumentazioneProcessSeeder::class);

        foreach ([
            'resp@example.com' => 'contabile responsabile',
            'admin@example.com' => 'amministratore',
            'direttore@example.com' => 'commerciale',
            'contabile@example.com' => 'contabile',
        ] as $email => $role) {
            Employee::create(['name' => $email, 'email' => $email, 'employee_roles' => [$role]]);
        }
    }

    private function item(int $order): ProcessTaskItem
    {
        $task = ProcessTask::query()->where('code', 'compliance-provvigioni-mese')->sole();

        return $task->processTaskItems()->where('ordine', $order)->sole();
    }

    public function test_the_monthly_steps_take_their_recipients_from_the_raci_of_the_step(): void
    {
        $this->assertSame(['A', 'C', 'I'], $this->item(10)->config['to_raci']);
        $this->assertSame(['R'], $this->item(20)->config['to_raci']);
        $this->assertSame(['A', 'I'], $this->item(30)->config['cc_raci']);
    }

    public function test_the_monthly_steps_run_only_on_their_day(): void
    {
        $this->assertTrue($this->item(10)->isDueOn(Carbon::parse('2026-11-03')));
        $this->assertFalse($this->item(10)->isDueOn(Carbon::parse('2026-11-04')));
        $this->assertTrue($this->item(20)->isDueOn(Carbon::parse('2026-11-05')));
        $this->assertTrue($this->item(30)->isDueOn(Carbon::parse('2026-11-10')));
        $this->assertFalse($this->item(30)->isDueOn(Carbon::parse('2026-11-11')));
    }

    public function test_on_the_3rd_the_list_goes_as_csv_to_the_accounting_manager_admin_and_sales(): void
    {
        Http::fake(['*/api/checks/provvigioni:check-maturate' => Http::response([
            'value' => 2, 'severity' => 'regular', 'details' => "2 provvigioni maturate.\n- Agente — ROSSI: 2",
            'attachment' => ['filename' => 'provvigioni-maturate-2026-11.csv', 'content' => "Provvigione;Pratica\nSP1;P1\n"],
        ])]);

        $sent = [];
        $sender = $this->mock(EmailSendingService::class, function (MockInterface $mock) use (&$sent) {
            $mock->shouldReceive('send')->times(3)->andReturnUsing(function (string $to, string $subject, string $body, array $files) use (&$sent) {
                $sent[$to] = [$subject, $files[0]['as'], file_get_contents($files[0]['path'])];
            });
        });

        app()->make(ExternalCheckRunner::class, ['emailSender' => $sender])->run($this->item(10));

        $this->assertEqualsCanonicalizing(['resp@example.com', 'admin@example.com', 'direttore@example.com'], array_keys($sent));
        $this->assertSame('provvigioni-maturate-2026-11.csv', $sent['resp@example.com'][1]);
        $this->assertStringContainsString('SP1;P1', $sent['resp@example.com'][2]);
    }

    public function test_on_the_5th_only_the_accountant_is_reminded(): void
    {
        Http::fake(['*/api/checks/provvigioni:check-maturate' => Http::response(['value' => 1, 'severity' => 'regular', 'details' => 'x'])]);

        $recipients = [];
        $sender = $this->mock(EmailSendingService::class, function (MockInterface $mock) use (&$recipients) {
            $mock->shouldReceive('send')->andReturnUsing(function (string $to) use (&$recipients) {
                $recipients[] = $to;
            });
        });

        app()->make(ExternalCheckRunner::class, ['emailSender' => $sender])->run($this->item(20));

        $this->assertSame(['contabile@example.com'], $recipients);
    }

    public function test_on_the_10th_proformas_are_queued_with_the_manager_and_admin_in_copy(): void
    {
        Queue::fake();
        $instance = ProcessInstance::create(['process_id' => $this->item(30)->task->process_id, 'status' => 'in_progress']);

        $summary = app(ExternalCommandRunner::class)->run($this->item(30), $instance);
        app(ExternalCommandRunner::class)->run($this->item(30), $instance);

        $call = OutboundCall::query()->sole();
        $this->assertSame('/api/commands/proformas:send-pending', $call->path);
        $this->assertEqualsCanonicalizing(['resp@example.com', 'admin@example.com'], explode(',', $call->payload['options']['cc']));
        $this->assertStringContainsString('accodato', $summary);
        Queue::assertPushed(SendOutboundCall::class, 1);
    }

    public function test_the_daily_process_goes_through_all_steps_and_runs_the_monthly_ones_only_on_their_day(): void
    {
        Queue::fake();
        Http::fake(['*/api/checks/*' => Http::response(['value' => 0, 'severity' => 'ok'])]);
        $process = ProcessTask::query()->where('code', 'compliance-provvigioni-mese')->sole()->process;

        Carbon::setTestNow('2026-11-10 06:00:00');
        $instance = app(StartProcessAction::class)->execute(null, $process->id);

        $answers = ProcessTaskItemAnswer::query()->where('process_instance_id', $instance->id)->orderBy('id')->pluck('value_text', 'process_task_item_id');
        $this->assertCount(6, $answers, 'Ogni azione automatica lascia una risposta.');
        $this->assertSame(2, $answers->filter(fn (string $text) => str_starts_with($text, 'Non previsto oggi'))->count(), 'Il 10 saltano il 3 e il 5.');
        $this->assertSame(1, OutboundCall::query()->where('event', 'external_command')->count());

        Carbon::setTestNow();
    }
}
