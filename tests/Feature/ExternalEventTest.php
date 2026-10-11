<?php

namespace Tests\Feature;

use App\Jobs\TaskEscalationWatchdogJob;
use App\Models\BusinessFunction;
use App\Models\Employee;
use App\Models\ExternalEvent;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskRaci;
use App\Models\ProcessTrigger;
use App\Models\User;
use App\Services\ExternalEventProcessor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Unico\Core\Models\Company;

class ExternalEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = ['X-Api-Key' => 'chiave-eventi'];

    private Process $process;

    private ProcessTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        config(['unico-core.bridge.api_key' => 'chiave-eventi', 'mail.default' => 'array']);
        $this->process = Process::create(['name' => 'Presa in carico pratica', 'code' => 'PRC-EXT-'.uniqid(), 'is_active' => true]);
        $this->task = ProcessTask::create(['process_id' => $this->process->id, 'name' => 'Presa in carico istruttore', 'ordine' => 1]);
    }

    private function trigger(array $attributes = []): ProcessTrigger
    {
        return ProcessTrigger::create($attributes + ['name' => 'Pratica perfezionata', 'model_class' => 'external', 'event_type' => 'external', 'external_event' => 'pratica.perfezionata', 'process_id' => $this->process->id, 'is_active' => true]);
    }

    private function event(array $override = []): array
    {
        return array_replace_recursive([
            'event_id' => 'pratica.perfezionata:FIN-2026-0300', 'event' => 'pratica.perfezionata', 'source' => 'unicoagent', 'subject_ref' => 'FIN-2026-0300',
            'data' => ['product' => ['type' => 'cessione'], 'amount' => 20000],
        ], $override);
    }

    public function test_an_event_starts_the_process_of_the_matching_trigger(): void
    {
        $this->trigger();
        $company = Company::create(['name' => 'Acme', 'vat_number' => '01234567890']);

        $response = $this->postJson('/api/events', $this->event(['data' => ['company_vat' => '01234567890']]), self::KEY);

        $response->assertStatus(202)->assertJson(['status' => 'processed', 'started' => 1, 'duplicate' => false]);
        $instance = ProcessInstance::sole();
        $this->assertSame('in_progress', $instance->status);
        $this->assertSame('unicoagent:FIN-2026-0300', $instance->external_ref);
        $this->assertSame('Pratica perfezionata FIN-2026-0300', $instance->title);
        $this->assertSame('cessione', $instance->external_data['product']['type']);
        $this->assertSame($company->id, (int) $instance->company_id);
        $this->assertSame($this->task->id, $instance->current_task_id);
        $this->assertNotNull($instance->currentTaskExecution);
        $this->assertNotNull($response->headers->get('X-Correlation-Id'));
    }

    public function test_the_same_event_is_not_processed_twice(): void
    {
        $this->trigger();

        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(202);
        $this->postJson('/api/events', $this->event(), self::KEY)->assertOk()->assertJson(['duplicate' => true, 'started' => 1]);

        $this->assertSame(1, ProcessInstance::count());
        $this->assertSame(1, ExternalEvent::count());
    }

    public function test_conditions_on_the_data_decide_whether_the_process_starts(): void
    {
        $this->trigger(['conditions' => [['field' => 'product.type', 'operator' => '=', 'value' => 'mutuo']]]);
        $this->trigger(['name' => 'Escluso', 'conditions' => [['field' => 'product.type', 'operator' => '!=', 'value' => 'cessione']]]);

        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(202)->assertJson(['status' => 'ignored', 'started' => 0]);
        $this->assertSame(0, ProcessInstance::count());

        $this->postJson('/api/events', $this->event(['event_id' => 'altro', 'data' => ['product' => ['type' => 'mutuo']]]), self::KEY)->assertJson(['started' => 2]);
    }

    public function test_inactive_triggers_and_other_events_are_ignored(): void
    {
        $this->trigger(['is_active' => false]);
        $this->trigger(['name' => 'Altro', 'external_event' => 'firma.completata']);

        $this->postJson('/api/events', $this->event(), self::KEY)->assertJson(['status' => 'ignored']);

        $this->assertSame(0, ProcessInstance::count());
    }

    public function test_the_key_is_required_and_the_body_is_validated(): void
    {
        $this->postJson('/api/events', $this->event())->assertUnauthorized();
        $this->postJson('/api/events', $this->event(), ['X-Api-Key' => 'sbagliata'])->assertUnauthorized();
        $this->postJson('/api/events', ['event' => 'Evento Con Spazi'], self::KEY)->assertStatus(422)->assertJsonValidationErrors(['event_id', 'event']);

        config(['unico-core.bridge.api_key' => null, 'services.bpm.api_key' => null]);
        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(503);
        $this->assertSame(0, ExternalEvent::count());
    }

    // --- Solleciti finché nessuno prende in carico ---

    private function functionWithMember(string $email): BusinessFunction
    {
        $function = BusinessFunction::create(['name' => 'Istruttoria '.uniqid(), 'code' => 'IST-'.uniqid()]);
        $employee = Employee::create(['name' => 'Istruttore '.uniqid(), 'email' => $email]);
        User::factory()->create(['email' => $email, 'profile_type' => 'employee', 'profile_id' => $employee->id]);
        $function->employees()->attach($employee->id, ['employee_type_id' => null]);

        return $function;
    }

    private function startUnclaimed(array $rules): ProcessInstance
    {
        $this->task->update(['escalation_rules' => $rules]);
        ProcessTaskRaci::create(['process_task_id' => $this->task->id, 'business_function_id' => $this->functionWithMember('istruttore1@example.com')->id, 'raci_role' => 'R']);
        ProcessTaskRaci::create(['process_task_id' => $this->task->id, 'business_function_id' => $this->functionWithMember('responsabile@example.com')->id, 'raci_role' => 'A']);
        $this->trigger();
        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(202);
        $instance = ProcessInstance::sole();
        // Due istruttori in funzione non bastano a un'assegnazione automatica; qui ce n'è uno solo: si libera la pratica.
        $instance->update(['current_assignee_id' => null, 'current_assignee_type' => null]);
        $instance->currentTaskExecution->forceFill(['created_at' => now()->subHours(30)])->saveQuietly();

        return $instance->fresh();
    }

    private function sentTo(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()
            ->map(fn ($m) => $m->getOriginalMessage()->getTo()[0]->getAddress())->all();
    }

    public function test_an_unclaimed_practice_is_chased_at_each_level_until_somebody_takes_it(): void
    {
        $instance = $this->startUnclaimed([
            ['level' => 1, 'delay_hours' => 4, 'action' => 'notify_assignee', 'until_claimed' => true],
            ['level' => 2, 'delay_hours' => 24, 'action' => 'notify_manager', 'until_claimed' => true],
        ]);

        (new TaskEscalationWatchdogJob)->handle();
        (new TaskEscalationWatchdogJob)->handle();

        $this->assertEqualsCanonicalizing(['istruttore1@example.com', 'responsabile@example.com'], $this->sentTo());
        $execution = $instance->fresh()->currentTaskExecution;
        $this->assertSame(2, (int) $execution->escalation_level);
        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('Pratica perfezionata FIN-2026-0300', $mail->getSubject());
    }

    public function test_the_chasing_stops_as_soon_as_the_practice_is_claimed(): void
    {
        $instance = $this->startUnclaimed([['level' => 1, 'delay_hours' => 4, 'action' => 'notify_assignee', 'until_claimed' => true]]);
        $user = User::factory()->create();
        $instance->update(['current_assignee_id' => $user->id, 'current_assignee_type' => get_class($user)]);

        (new TaskEscalationWatchdogJob)->handle();

        $this->assertSame([], $this->sentTo());
        $this->assertSame(0, (int) $instance->fresh()->currentTaskExecution->escalation_level);
    }

    public function test_the_manager_email_of_the_rule_wins_and_a_postponed_step_is_left_alone(): void
    {
        $instance = $this->startUnclaimed([['level' => 1, 'delay_hours' => 4, 'action' => 'notify_manager', 'manager_email' => 'direzione@example.com', 'until_claimed' => true]]);
        $instance->currentTaskExecution->update(['postponed_until' => now()->addDays(2)->toDateString()]);

        (new TaskEscalationWatchdogJob)->handle();
        $this->assertSame([], $this->sentTo());

        $instance->currentTaskExecution->update(['postponed_until' => now()->subDay()->toDateString()]);
        (new TaskEscalationWatchdogJob)->handle();
        $this->assertSame(['direzione@example.com'], $this->sentTo());
    }

    public function test_a_failed_event_answers_503_and_is_processed_again_when_resent(): void
    {
        $this->trigger();
        $this->app->bind(\App\Filament\Actions\StartProcessAction::class, fn () => new class extends \App\Filament\Actions\StartProcessAction
        {
            public function execute(?\Illuminate\Database\Eloquent\Model $subject, int $processId): ProcessInstance
            {
                throw new \RuntimeException('database giù');
            }
        });

        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(503)->assertJson(['status' => 'failed']);
        $this->assertSame('failed', ExternalEvent::sole()->status);

        $this->app->forgetInstance(ExternalEventProcessor::class);
        $this->app->bind(\App\Filament\Actions\StartProcessAction::class, \App\Filament\Actions\StartProcessAction::class);
        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(202)->assertJson(['status' => 'processed', 'started' => 1, 'retried' => true]);

        $this->assertSame(1, ProcessInstance::count());
        $this->assertSame(1, ExternalEvent::count());
    }
}
