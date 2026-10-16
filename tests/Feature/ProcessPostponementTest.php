<?php

namespace Tests\Feature;

use App\Filament\Resources\ProcessEmailReplies\Pages\ListProcessEmailReplies;
use App\Jobs\TaskEscalationWatchdogJob;
use App\Listeners\HandleProcessEmailReply;
use App\Models\Process;
use App\Models\ProcessEmailReply;
use App\Models\ProcessInstance;
use App\Models\ProcessPostponement;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use App\Models\User;
use App\Services\Replies\Interpretation;
use App\Services\Replies\ReplyInterpreter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Events\IncomingEmailReceived;
use Unico\Core\Models\Company;
use Unico\Core\Models\IncomingEmail;

class ProcessPostponementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ?Interpretation $ai = null;

    private ProcessInstance $instance;

    private ProcessTask $task;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        $this->app->bind(ReplyInterpreter::class, fn () => new class($this) implements ReplyInterpreter
        {
            public function __construct(private ProcessPostponementTest $test) {}

            public function interpret(string $reply, ?string $question = null): ?Interpretation
            {
                return $this->test->ai();
            }
        });
        $this->company = Company::create(['name' => 'Acme '.uniqid()]);
    }

    public function ai(): ?Interpretation
    {
        return $this->ai;
    }

    private function process(array $config = [], ?string $hardDeadline = null, ?string $dueAt = '+5 days'): void
    {
        $process = Process::create(['name' => 'Onboarding', 'code' => 'PRC-PP-'.uniqid(), 'is_active' => true]);
        $this->task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Visita', 'ordine' => 1]);
        ProcessTask::create(['process_id' => $process->id, 'name' => 'Dopo', 'ordine' => 2]);
        ProcessTaskItem::create(['process_task_id' => $this->task->id, 'name' => 'Conferma visita', 'action_type' => 'await_email_reply', 'is_required' => true, 'config' => $config + ['on_postpone' => 'grant']]);
        $this->instance = ProcessInstance::create(['process_id' => $process->id, 'current_task_id' => $this->task->id, 'status' => 'in_progress', 'company_id' => $this->company->id, 'hard_deadline_at' => $hardDeadline]);
        $this->instance->currentTaskExecution->update(['due_at' => $dueAt ? now()->modify($dueAt) : null]);
    }

    private function reply(string $date, int $confidence = 90): void
    {
        $this->ai = new Interpretation('postpone', $confidence, 'Chiede di spostare la visita', $date, 'ai');
        $execution = $this->instance->currentTaskExecution;
        $email = IncomingEmail::create([
            'company_id' => $this->company->id, 'message_id' => 'm-'.uniqid().'@x.test', 'from_email' => 'cliente@example.com', 'subject' => 'Re: Visita',
            'body_text' => 'Possiamo spostare a dopo il 20? Grazie', 'received_at' => now(), 'in_reply_to' => "<bpm-{$this->instance->id}-{$execution->id}-abc123def4@unico.test>",
        ]);
        app(HandleProcessEmailReply::class)->handle(new IncomingEmailReceived($email));
    }

    public function test_a_postponement_within_the_limits_is_granted_and_confirmed_by_email(): void
    {
        $this->process();
        $date = now()->addDays(12)->toDateString();

        $this->reply($date);

        $execution = $this->instance->fresh()->currentTaskExecution;
        $this->assertSame($date, $execution->postponed_until->toDateString());
        $this->assertTrue($execution->effectiveDueAt()->isSameDay(now()->addDays(12)));
        $this->assertFalse($execution->isOverdue());
        $this->assertTrue($execution->isPostponed());
        $reply = ProcessEmailReply::sole();
        $this->assertSame('applied', $reply->status);
        $this->assertSame('postpone', $reply->intent);
        $this->assertSame(0, ProcessTaskItemAnswer::count());
        $this->assertSame('Visita', $this->instance->fresh()->currentTask->name);
        $row = ProcessPostponement::sole();
        $this->assertNull($row->granted_by);
        $this->assertSame($reply->id, $row->process_email_reply_id);

        $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertStringContainsString(now()->addDays(12)->format('d/m/Y'), quoted_printable_decode($sent->first()->getOriginalMessage()->getBody()->bodyToString()));
    }

    public function test_requests_outside_the_limits_or_without_the_grant_choice_wait_for_an_operator(): void
    {
        // Oltre i giorni consentiti.
        $this->process(['max_postpone_days' => 10]);
        $this->reply(now()->addDays(20)->toDateString());
        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);
        $this->assertSame(0, ProcessPostponement::count());

        // Oltre la data tassativa della pratica.
        ProcessEmailReply::query()->delete();
        $this->process([], now()->addDays(8)->toDateString());
        $this->reply(now()->addDays(10)->toDateString());
        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);

        // Senza la scelta «concedi»: decide un operatore.
        ProcessEmailReply::query()->delete();
        $this->process(['on_postpone' => 'review']);
        $this->reply(now()->addDays(8)->toDateString());
        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);

        // Senza data o con confidenza bassa.
        ProcessEmailReply::query()->delete();
        $this->process();
        $this->reply(now()->addDays(8)->toDateString(), 50);
        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);

        // Data passata.
        ProcessEmailReply::query()->delete();
        $this->process();
        $this->reply(now()->subDay()->toDateString());
        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);
    }

    public function test_the_number_of_postponements_per_step_is_limited(): void
    {
        $this->process(['max_postponements' => 1]);
        $this->reply(now()->addDays(8)->toDateString());
        $this->assertSame('applied', ProcessEmailReply::sole()->status);

        $this->reply(now()->addDays(15)->toDateString());

        $this->assertSame(1, ProcessPostponement::count());
        $this->assertSame('needs_review', ProcessEmailReply::latest('id')->first()->status);
    }

    public function test_the_confirmation_email_can_be_switched_off(): void
    {
        $this->process(['confirm_postpone_email' => false]);

        $this->reply(now()->addDays(8)->toDateString());

        $this->assertSame(1, ProcessPostponement::count());
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_a_postponement_never_moves_the_deadline_backwards(): void
    {
        $this->process(dueAt: '+30 days');
        $execution = $this->instance->currentTaskExecution;
        $execution->update(['postponed_until' => now()->addDays(10)->toDateString()]);

        $this->assertTrue($execution->fresh()->effectiveDueAt()->isSameDay(now()->addDays(30)));

        $this->process(dueAt: null);
        $execution = $this->instance->currentTaskExecution;
        $execution->update(['postponed_until' => now()->addDays(10)->toDateString()]);
        $this->assertTrue($execution->fresh()->effectiveDueAt()->isSameDay(now()->addDays(10)));
    }

    public function test_escalation_pauses_while_the_step_is_postponed(): void
    {
        $this->process();
        $this->task->update(['escalation_rules' => [['level' => 1, 'delay_hours' => 1, 'action' => 'notify_assignee']]]);
        $execution = $this->instance->currentTaskExecution;
        $execution->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        $execution->update(['postponed_until' => now()->addDays(3)->toDateString()]);

        (new TaskEscalationWatchdogJob)->handle();
        $this->assertSame(0, (int) $execution->fresh()->escalation_level);

        $execution->update(['postponed_until' => now()->subDay()->toDateString()]);
        (new TaskEscalationWatchdogJob)->handle();
        $this->assertSame(1, (int) $execution->fresh()->escalation_level);
    }

    public function test_an_operator_grants_the_postponement_and_can_move_the_hard_deadline(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($operator = User::factory()->create(['role' => 'admin']));
        $this->process(['max_postpone_days' => 5], now()->addDays(10)->toDateString());
        $this->reply(now()->addDays(25)->toDateString());
        $reply = ProcessEmailReply::sole();
        $this->assertSame('needs_review', $reply->status);
        $date = now()->addDays(25)->toDateString();

        Livewire::test(ListProcessEmailReplies::class)
            ->callAction(TestAction::make('rinvio')->table($reply), ['data' => $date, 'sposta_pratica' => true])
            ->assertNotified('Rinvio concesso');

        $this->assertSame('applied', $reply->fresh()->status);
        $this->assertSame($operator->id, ProcessPostponement::sole()->granted_by);
        $this->assertTrue(ProcessPostponement::sole()->moved_hard_deadline);
        $this->assertSame($date, $this->instance->fresh()->hard_deadline_at->toDateString());
        $this->assertSame($date, $this->instance->fresh()->currentTaskExecution->postponed_until->toDateString());
    }
}
