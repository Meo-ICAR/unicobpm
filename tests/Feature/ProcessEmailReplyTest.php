<?php

namespace Tests\Feature;

use App\Filament\Resources\ProcessEmailReplies\Pages\ListProcessEmailReplies;
use App\Listeners\HandleProcessEmailReply;
use App\Models\Process;
use App\Models\ProcessEmailReply;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use App\Models\User;
use App\Neuron\EmailReplyAgent;
use App\Services\EmailSendingService;
use App\Services\Replies\CompositeReplyInterpreter;
use App\Services\Replies\Interpretation;
use App\Services\Replies\ReplyInterpreter;
use App\Services\Replies\RuleBasedReplyInterpreter;
use App\Support\ProcessThread;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;
use Unico\Core\Events\IncomingEmailReceived;
use Unico\Core\Models\Company;
use Unico\Core\Models\IncomingEmail;

class ProcessEmailReplyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ?Interpretation $ai = null;

    private ProcessInstance $instance;

    private ProcessTaskItem $item;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // L'AI è sostituita da un finto: nei test non si chiama nessun servizio.
        $this->app->bind(EmailReplyAgent::class, fn () => throw new \RuntimeException('AI non disponibile nei test'));
        $this->app->bind(ReplyInterpreter::class, fn () => new CompositeReplyInterpreter([
            new RuleBasedReplyInterpreter,
            new class($this) implements ReplyInterpreter
            {
                public function __construct(private ProcessEmailReplyTest $test) {}

                public function interpret(string $reply, ?string $question = null): ?Interpretation
                {
                    return $this->test->ai();
                }
            },
        ]));
        $this->company = Company::create(['name' => 'Acme '.uniqid()]);
    }

    public function ai(): ?Interpretation
    {
        return $this->ai;
    }

    private function process(array $itemConfig = []): void
    {
        $process = Process::create(['name' => 'Onboarding', 'code' => 'PRC-RPL-'.uniqid(), 'is_active' => true]);
        $first = ProcessTask::create(['process_id' => $process->id, 'name' => 'Attesa conferma', 'ordine' => 1]);
        ProcessTask::create(['process_id' => $process->id, 'name' => 'Dopo', 'ordine' => 2]);
        $this->item = ProcessTaskItem::create([
            'process_task_id' => $first->id, 'name' => 'Conferma visita', 'action_type' => 'await_email_reply', 'is_required' => true,
            'config' => $itemConfig + ['question' => 'Conferma la visita del 12/11'],
        ]);
        $this->instance = ProcessInstance::create(['process_id' => $process->id, 'current_task_id' => $first->id, 'status' => 'in_progress']);
    }

    private function execution(): int
    {
        return $this->instance->currentTaskExecution->id;
    }

    private function email(string $body, ?string $inReplyTo = null, array $attributes = []): IncomingEmail
    {
        return IncomingEmail::create($attributes + [
            'company_id' => $this->company->id, 'message_id' => 'm-'.uniqid().'@x.test', 'from_email' => 'cliente@example.com', 'subject' => 'Re: Conferma visita',
            'body_text' => $body, 'received_at' => now(), 'in_reply_to' => $inReplyTo ?? '<bpm-'.$this->instance->id.'-'.$this->execution().'-abc123def4@unico.test>',
        ]);
    }

    private function receive(IncomingEmail $email): void
    {
        app(HandleProcessEmailReply::class)->handle(new IncomingEmailReceived($email));
    }

    public function test_a_short_confirmation_advances_the_process_without_the_ai(): void
    {
        $this->process();

        $this->receive($this->email("Sì, confermo.\n\nIl giorno 10/11/2026 Acme ha scritto:\n> Conferma la visita del 12/11"));

        $reply = ProcessEmailReply::sole();
        $this->assertSame('applied', $reply->status);
        $this->assertSame('confirm', $reply->intent);
        $this->assertSame('rule', $reply->source);
        $this->assertSame('Sì, confermo.', $reply->excerpt);
        $answer = ProcessTaskItemAnswer::sole();
        $this->assertSame('procedural', $answer->operator_type);
        $this->assertSame('email_reply.rule', $answer->operator_label);
        $this->assertTrue($answer->value_boolean);
        $this->assertSame('Dopo', $this->instance->fresh()->currentTask->name);
    }

    public function test_a_confident_ai_interpretation_advances_and_a_doubtful_one_waits_for_an_operator(): void
    {
        $this->process();
        $this->ai = new Interpretation('confirm', 95, 'Conferma la visita', source: 'ai');
        $this->receive($this->email('Per noi va bene giovedì come concordato, ci vediamo allora.'));

        $this->assertSame('applied', ProcessEmailReply::sole()->status);
        $this->assertSame('ai', ProcessTaskItemAnswer::sole()->operator_type);
        $this->assertSame('Dopo', $this->instance->fresh()->currentTask->name);

        ProcessEmailReply::query()->delete();
        ProcessTaskItemAnswer::query()->delete();
        $this->process();
        $this->ai = new Interpretation('confirm', 60, 'Forse conferma', source: 'ai');
        $this->receive($this->email('Credo di sì ma devo sentire il collega, vi faccio sapere.'));

        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);
        $this->assertSame(0, ProcessTaskItemAnswer::count());
        $this->assertSame('Attesa conferma', $this->instance->fresh()->currentTask->name);
    }

    public function test_a_reply_nobody_understands_waits_for_an_operator(): void
    {
        $this->process();
        $this->ai = null;

        $this->receive($this->email('Buongiorno, vi richiamo domani per parlarne a voce.'));

        $reply = ProcessEmailReply::sole();
        $this->assertSame('needs_review', $reply->status);
        $this->assertNull($reply->intent);
        $this->assertSame($this->item->id, $reply->process_task_item_id);
    }

    public function test_a_refusal_follows_the_choice_of_the_action(): void
    {
        $this->process();
        $this->receive($this->email('Non accetto la data proposta.'));
        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);
        $this->assertSame('refuse', ProcessEmailReply::sole()->intent);
        $this->assertSame(0, ProcessTaskItemAnswer::count());

        ProcessEmailReply::query()->delete();
        $this->process(['on_refuse' => 'answer']);
        $this->receive($this->email('Non accetto la data proposta.'));
        $this->assertSame('applied', ProcessEmailReply::sole()->status);
        $this->assertFalse(ProcessTaskItemAnswer::sole()->value_boolean);
        $this->assertSame('Dopo', $this->instance->fresh()->currentTask->name);

        ProcessEmailReply::query()->delete();
        $this->process(['on_refuse' => 'suspend']);
        $this->receive($this->email('Non accetto la data proposta.'));
        $this->assertSame('suspended', $this->instance->fresh()->status);
    }

    public function test_automatic_advance_can_be_switched_off(): void
    {
        $this->process(['auto' => false]);

        $this->receive($this->email('Sì'));

        $this->assertSame('needs_review', ProcessEmailReply::sole()->status);
        $this->assertSame(0, ProcessTaskItemAnswer::count());
    }

    public function test_emails_that_do_not_belong_to_a_process_are_left_alone(): void
    {
        $this->process();

        $this->receive($this->email('Sì confermo', '<altro@x.test>'));
        $this->receive($this->email('Sì confermo', '<bpm-999999-1-abc123def4@unico.test>'));
        $this->receive($this->email('Sì confermo', attributes: ['classification' => 'bounce']));

        $this->assertSame(0, ProcessEmailReply::count());
        $this->assertSame('Attesa conferma', $this->instance->fresh()->currentTask->name);
    }

    public function test_the_subject_tag_is_the_fallback_for_emails_sent_in_another_way(): void
    {
        $this->process();

        $this->receive($this->email('Confermo', '<altro@x.test>', ['subject' => 'Re: Visita [ID: '.$this->instance->id.']']));

        $this->assertSame('applied', ProcessEmailReply::sole()->status);
    }

    public function test_the_same_email_is_processed_once_and_a_late_reply_is_only_recorded(): void
    {
        $this->process();
        $email = $this->email('Sì');
        $this->receive($email);
        $this->receive($email);
        $this->assertSame(1, ProcessEmailReply::count());
        $this->assertSame(1, ProcessTaskItemAnswer::count());

        // L'esecuzione di prima è conclusa: una nuova risposta a quel passo non fa avanzare niente.
        $late = $this->email('Sì', '<bpm-'.$this->instance->id.'-'.ProcessEmailReply::sole()->process_task_execution_id.'-abc123def4@unico.test>');
        $this->receive($late);

        $this->assertSame(2, ProcessEmailReply::count());
        $this->assertSame('no_action', ProcessEmailReply::latest('id')->first()->status);
        $this->assertSame(1, ProcessTaskItemAnswer::count());
    }

    public function test_the_thread_id_round_trips(): void
    {
        config(['services.bpm.thread_domain' => 'bpm.test']);

        $id = ProcessThread::messageId(42, 7);

        $this->assertMatchesRegularExpression('/^bpm-42-7-[a-z0-9]{10}@bpm\.test$/', $id);
        $this->assertSame(['instance' => 42, 'execution' => 7], ProcessThread::parse('<altro@x.it> <'.$id.'>'));
        $this->assertNull(ProcessThread::parse('<altro@x.it>'));
        $this->assertSame(['instance' => 5, 'execution' => null], ProcessThread::parse('<'.ProcessThread::messageId(5, null).'>'));
    }

    public function test_an_operator_decides_from_the_list(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->process();
        $this->receive($this->email('Buongiorno, vi richiamo domani per parlarne a voce.'));
        $reply = ProcessEmailReply::sole();

        Livewire::test(ListProcessEmailReplies::class)
            ->assertCanSeeTableRecords([$reply])
            ->callAction(TestAction::make('conferma')->table($reply), ['nota' => 'Telefonata con il cliente'])
            ->assertNotified('Risposta confermata: il processo avanza');

        $reply->refresh();
        $this->assertSame('applied', $reply->status);
        $this->assertNotNull($reply->decided_by);
        $answer = ProcessTaskItemAnswer::sole();
        $this->assertSame('human', $answer->operator_type);
        $this->assertStringContainsString('Telefonata con il cliente', $answer->value_text);
        $this->assertSame('Dopo', $this->instance->fresh()->currentTask->name);
    }

    public function test_an_operator_can_ignore_a_reply(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->process();
        $this->receive($this->email('Buongiorno, vi richiamo domani.'));
        $reply = ProcessEmailReply::sole();

        Livewire::test(ListProcessEmailReplies::class)->callAction(TestAction::make('ignora')->table($reply))->assertNotified('Risposta ignorata');

        $this->assertSame('ignored', $reply->fresh()->status);
        $this->assertSame(0, ProcessTaskItemAnswer::count());
    }

    public function test_outgoing_process_emails_carry_the_thread_message_id(): void
    {
        config(['mail.default' => 'array', 'services.bpm.thread_domain' => 'bpm.test']);

        app(EmailSendingService::class)->send('cliente@example.com', 'Conferma visita', 'Testo', [], null, ['instance' => 5, 'execution' => 9]);
        app(EmailSendingService::class)->send('cliente@example.com', 'Senza thread', 'Testo');

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $ids = $messages->map(fn ($m) => $m->getOriginalMessage()->getHeaders()->get('Message-ID')?->getBodyAsString())->all();

        $this->assertMatchesRegularExpression('/^<bpm-5-9-[a-z0-9]{10}@bpm\.test>$/', $ids[0]);
        $this->assertDoesNotMatchRegularExpression('/bpm-/', (string) $ids[1]);
        $this->assertSame(['instance' => 5, 'execution' => 9], ProcessThread::parse($ids[0]));
    }
}
