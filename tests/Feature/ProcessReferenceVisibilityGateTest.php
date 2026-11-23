<?php

namespace Tests\Feature;

use App\Filament\Actions\CompleteCurrentTaskAction;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ExternalEvent;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use App\Models\ProcessTrigger;
use App\Services\EmailSendingService;
use App\Services\ExternalEventProcessor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;
use Unico\Core\Models\Company;
use Unico\Core\Models\IncomingEmail;

/** Codice pratica, copia di visibilità delle email, email di origine degli eventi e gate bloccanti dei processi. */
class ProcessReferenceVisibilityGateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function process(array $attributes = []): Process
    {
        $process = Process::create($attributes + ['name' => 'Reclutamento', 'code' => 'PRC-RV-'.uniqid(), 'is_active' => true]);
        ProcessTask::create(['process_id' => $process->id, 'name' => 'Fase 1', 'ordine' => 10]);

        return $process;
    }

    public function test_the_reference_code_is_assigned_per_year_and_prefix(): void
    {
        $process = $this->process(['reference_prefix' => 'REC']);

        $first = ProcessInstance::create(['process_id' => $process->id]);
        $second = ProcessInstance::create(['process_id' => $process->id]);

        $this->assertSame('REC-'.now()->year.'-0001', $first->reference);
        $this->assertSame('REC-'.now()->year.'-0002', $second->reference);
        $this->assertNull(ProcessInstance::create(['process_id' => $this->process()->id])->reference, 'senza prefisso nessun codice');
    }

    public function test_process_emails_carry_the_reference_in_the_subject_and_a_hidden_copy_for_unicopec(): void
    {
        config(['mail.default' => 'array', 'mail.always_bcc' => null]);
        $process = $this->process(['reference_prefix' => 'REC', 'visibility_bcc' => 'recruiting@example.com, non-valida']);
        $instance = ProcessInstance::create(['process_id' => $process->id]);

        app(EmailSendingService::class)->send('dest@example.com', 'Documenti mancanti', 'Corpo', [], null, ['instance' => $instance->id]);

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame("[{$instance->reference}] Documenti mancanti", $sent->getSubject());
        $this->assertSame(['recruiting@example.com'], array_map(fn ($a) => $a->getAddress(), $sent->getBcc()));
    }

    public function test_the_copy_is_not_added_when_the_presidio_is_already_the_recipient(): void
    {
        config(['mail.default' => 'array', 'mail.always_bcc' => null]);
        $instance = ProcessInstance::create(['process_id' => $this->process(['visibility_bcc' => 'recruiting@example.com'])->id]);

        app(EmailSendingService::class)->send('recruiting@example.com', 'Esito', 'Corpo', [], null, ['instance' => $instance->id]);

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame([], $sent->getBcc());
    }

    public function test_an_email_event_starts_the_process_and_keeps_the_reference_to_the_email_of_unicopec(): void
    {
        $process = $this->process(['reference_prefix' => 'REC']);
        ProcessTrigger::create(['name' => 'Candidatura', 'model_class' => 'external', 'event_type' => 'external', 'external_event' => 'email.richiesta', 'process_id' => $process->id,
            'conditions' => [['field' => 'richiesta', 'value' => 'candidatura_rete', 'operator' => '=']], 'is_active' => true]);
        $company = Company::create(['name' => 'Acme '.uniqid()]);
        $email = IncomingEmail::create(['company_id' => $company->id, 'message_id' => 'm-'.uniqid().'@x.test', 'from_email' => 'candidato@example.com', 'subject' => 'Candidatura', 'body_text' => 'Mi candido', 'received_at' => now()]);

        $event = ExternalEvent::create(['event_id' => 'ev-'.uniqid(), 'name' => 'email.richiesta', 'source' => 'unicopec', 'subject_ref' => (string) $email->id, 'data' => ['richiesta' => 'candidatura_rete'], 'occurred_at' => now(), 'status' => ExternalEvent::RECEIVED]);
        app(ExternalEventProcessor::class)->process($event);

        $instance = ProcessInstance::sole();
        $this->assertSame($email->id, $instance->incoming_email_id);
        $this->assertStringStartsWith('REC-', $instance->reference);
    }

    public function test_a_blocking_gate_is_approved_only_with_every_condition_met(): void
    {
        $process = $this->process();
        $task = $process->tasks()->first();
        $checklist = Checklist::create(['name' => 'Gate G1', 'code' => 'chk_test_g1', 'is_active' => true, 'is_gate' => true]);
        $voci = collect(['a', 'b', 'c'])->map(fn (string $c) => ChecklistItem::create(['checklist_id' => $checklist->id, 'item_code' => $c, 'ordine' => 10, 'name' => $c, 'question' => $c.'?', 'type' => 'boolean', 'is_required' => true]));
        $item = ProcessTaskItem::create(['process_task_id' => $task->id, 'name' => 'Gate G1', 'action_type' => 'fill_checklist', 'checklist_id' => $checklist->id, 'is_required' => true]);
        $instance = ProcessInstance::create(['process_id' => $process->id, 'current_task_id' => $task->id, 'status' => 'in_progress']);
        $execution = $instance->currentTaskExecution->id;

        $run = fn (array $answers) => (new ReflectionMethod(CompleteCurrentTaskAction::class, 'processChecklist'))->invoke(null, $instance, $execution, $item->fresh('checklist'), $answers);

        $run([$voci[0]->id => true, $voci[1]->id => true, $voci[2]->id => false]);
        $this->assertSame(0, ProcessTaskItemAnswer::count(), 'una condizione a no: il gate non si approva');

        $run([$voci[0]->id => true, $voci[1]->id => true, $voci[2]->id => true]);
        $this->assertSame(1, ProcessTaskItemAnswer::count(), 'tutte le condizioni a sì: gate approvato');
    }
}
