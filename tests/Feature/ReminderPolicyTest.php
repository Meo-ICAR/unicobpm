<?php

namespace Tests\Feature;

use App\Enums\Severity;
use App\Filament\Resources\Processes\Pages\EditProcess;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Models\User;
use App\Services\EmailSendingService;
use App\Services\ExternalCheckRunner;
use App\Services\ReminderPolicy;
use Database\Seeders\ComplianceDocumentazioneProcessSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

class ReminderPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ProcessTaskItem $item;

    private ReminderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ComplianceDocumentazioneProcessSeeder::class);
        $this->item = ProcessTask::query()->where('code', 'compliance-fatture-acquisto')->sole()->processTaskItems()->sole();
        $this->policy = new ReminderPolicy;
        Carbon::setTestNow('2026-11-02 06:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function sendIfDue(Severity $checked, string $day): ?Severity
    {
        Carbon::setTestNow("{$day} 06:00:00");
        $toSend = $this->policy->severityToSend($this->item, $checked);

        if ($toSend !== null) {
            $this->policy->recordSent($this->item, $checked, $toSend);
        }

        return $toSend;
    }

    public function test_the_same_problem_is_not_sent_again_the_next_day_and_escalates_after_the_repeat_days(): void
    {
        $this->assertSame(Severity::Regular, $this->sendIfDue(Severity::Regular, '2026-11-02'));
        $this->assertNull($this->sendIfDue(Severity::Regular, '2026-11-03'));
        $this->assertNull($this->sendIfDue(Severity::Regular, '2026-11-08'));
        $this->assertSame(Severity::Warning, $this->sendIfDue(Severity::Regular, '2026-11-09'), 'Regular: dopo 7 giorni sale a warning');
        $this->assertNull($this->sendIfDue(Severity::Regular, '2026-11-11'));
        $this->assertSame(Severity::Alert, $this->sendIfDue(Severity::Regular, '2026-11-12'), 'Warning: dopo 3 giorni sale ad alert');
        $this->assertNull($this->sendIfDue(Severity::Regular, '2026-11-13'));
        $this->assertSame(Severity::Alert, $this->sendIfDue(Severity::Regular, '2026-11-14'), 'Alert: si rimanda ogni 2 giorni');
    }

    public function test_a_worse_check_is_sent_at_once_and_a_solved_one_restarts_the_series(): void
    {
        $this->assertSame(Severity::Regular, $this->sendIfDue(Severity::Regular, '2026-11-02'));
        $this->assertSame(Severity::Alert, $this->sendIfDue(Severity::Alert, '2026-11-03'));

        $this->policy->resolve($this->item);

        $this->assertSame(Severity::Regular, $this->sendIfDue(Severity::Regular, '2026-11-04'), 'Risolto e ricomparso: riparte dal suo grado');
    }

    public function test_repeat_days_come_from_the_action_then_the_process_then_the_defaults(): void
    {
        $this->assertSame(7, $this->policy->repeatDays($this->item, Severity::Regular));

        $this->item->task->process->update(['reminder_repeat_days' => ['regular' => 10]]);
        $this->item->refresh();
        $this->assertSame(10, $this->policy->repeatDays($this->item, Severity::Regular));
        $this->assertSame(3, $this->policy->repeatDays($this->item, Severity::Warning), 'Il grado non indicato resta al predefinito');

        $this->item->update(['config' => [...$this->item->config, 'repeat_days' => ['regular' => 4]]]);
        $this->assertSame(4, $this->policy->repeatDays($this->item, Severity::Regular));
    }

    public function test_the_runner_sends_once_then_skips_the_next_day_and_escalates_a_week_later(): void
    {
        config(['services.fallback_recipient' => 'admin@example.com']);
        Http::fake(['*/api/checks/*' => Http::response(['value' => 26, 'severity' => 'regular', 'details' => 'ferma'])]);

        $subjects = [];
        $sender = $this->mock(EmailSendingService::class, function (MockInterface $mock) use (&$subjects) {
            $mock->shouldReceive('send')->andReturnUsing(function (string $to, string $subject) use (&$subjects) {
                $subjects[] = $subject;
            });
        });
        $runner = app()->make(ExternalCheckRunner::class, ['emailSender' => $sender]);

        Carbon::setTestNow('2026-11-02 06:00:00');
        $runner->run($this->item);
        Carbon::setTestNow('2026-11-03 06:00:00');
        $skipped = $runner->run($this->item);
        Carbon::setTestNow('2026-11-09 06:00:00');
        $runner->run($this->item);

        $this->assertCount(2, $subjects);
        $this->assertStringStartsWith('Promemoria', $subjects[0]);
        $this->assertStringStartsWith('Attenzione', $subjects[1], 'Dopo 7 giorni il promemoria sale a «Attenzione»');
        $this->assertStringContainsString('avviso già inviato', $skipped);
    }

    public function test_the_repeat_days_are_edited_from_the_process_form(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $process = $this->item->task->process;

        Livewire::test(EditProcess::class, ['record' => $process->getKey()])
            ->fillForm(['reminder_repeat_days' => ['regular' => 14, 'warning' => 5, 'alert' => 1]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(['regular' => 14, 'warning' => 5, 'alert' => 1], $process->fresh()->reminder_repeat_days);
    }
}
