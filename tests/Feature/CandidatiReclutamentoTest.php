<?php

namespace Tests\Feature;

use App\Models\ExternalEvent;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTrigger;
use App\Services\ExternalEventProcessor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Company;
use Unico\Core\Models\Fornitore;
use Unico\Core\Models\IncomingEmail;

/** I candidati alla rete commerciale sono produttori non operativi (is_active = false, decorrenza vuota) e hanno una retention di 6 mesi. */
class CandidatiReclutamentoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Process $process;

    protected function setUp(): void
    {
        parent::setUp();

        $this->process = Process::create(['name' => 'Reclutamento', 'code' => 'PRC-RECLUTAMENTO-RETE', 'is_active' => true, 'reference_prefix' => 'REC', 'target_model' => 'fornitore']);
        ProcessTask::create(['process_id' => $this->process->id, 'name' => 'Candidatura', 'ordine' => 20]);
        ProcessTrigger::create(['name' => 'Candidatura', 'model_class' => 'external', 'event_type' => 'external', 'external_event' => 'email.richiesta', 'process_id' => $this->process->id,
            'conditions' => [['field' => 'richiesta', 'value' => 'candidatura_rete', 'operator' => '=']], 'is_active' => true]);
    }

    private function candidacy(string $from, string $name = 'Mario Rossi'): ExternalEvent
    {
        $company = Company::first() ?? Company::create(['name' => 'Acme '.uniqid()]);
        $email = IncomingEmail::create(['company_id' => $company->id, 'message_id' => 'm-'.uniqid().'@x.test', 'from_email' => $from, 'from_name' => $name, 'subject' => 'Candidatura', 'body_text' => 'Mi candido', 'received_at' => now()]);
        $event = ExternalEvent::create(['event_id' => 'ev-'.uniqid(), 'name' => 'email.richiesta', 'source' => 'unicopec', 'subject_ref' => (string) $email->id, 'data' => ['richiesta' => 'candidatura_rete'], 'occurred_at' => now(), 'status' => ExternalEvent::RECEIVED]);

        return app(ExternalEventProcessor::class)->process($event);
    }

    public function test_a_candidacy_email_creates_the_candidate_as_an_inactive_producer_without_effective_date(): void
    {
        $this->candidacy('mario.rossi@example.com');

        $candidate = Fornitore::where('email_private', 'mario.rossi@example.com')->sole();
        $this->assertFalse((bool) $candidate->is_active);
        $this->assertNull($candidate->stipulated_at);
        $this->assertSame('MARIO ROSSI', $candidate->name);

        $instance = ProcessInstance::sole();
        $this->assertSame($candidate->id, (int) $instance->subject_id);
        $this->assertSame(Fornitore::class, $instance->subject_type);
        $this->assertStringStartsWith('REC-', $instance->reference);
    }

    public function test_a_second_email_from_the_same_candidate_reuses_the_producer(): void
    {
        $this->candidacy('mario.rossi@example.com');
        $this->candidacy('mario.rossi@example.com');

        $this->assertSame(1, Fornitore::where('email_private', 'mario.rossi@example.com')->count());
    }

    public function test_an_email_from_an_operational_producer_is_not_a_candidacy(): void
    {
        Fornitore::create(['name' => 'BIANCHI LUCA', 'email_private' => 'luca@example.com', 'is_active' => true, 'stipulated_at' => '2024-05-01']);

        $this->candidacy('luca@example.com', 'Luca Bianchi');

        $this->assertSame(0, ProcessInstance::count());
    }

    private function closedCandidacy(string $email, string $status, string $updated): Fornitore
    {
        $candidate = Fornitore::create(['name' => 'ROSSI '.strtoupper(uniqid()), 'email_private' => $email, 'is_active' => false, 'cf' => 'RSSMRA80A01H501U']);
        $instance = ProcessInstance::create(['process_id' => $this->process->id, 'subject_type' => Fornitore::class, 'subject_id' => $candidate->id, 'status' => $status, 'title' => 'Mario Rossi']);
        ProcessInstance::whereKey($instance->id)->update(['status' => $status, 'updated_at' => $updated]);

        return $candidate;
    }

    public function test_the_retention_removes_only_candidacies_closed_for_more_than_six_months(): void
    {
        $old = $this->closedCandidacy('vecchio@example.com', 'cancelled', now()->subMonths(7)->toDateTimeString());
        $recent = $this->closedCandidacy('recente@example.com', 'cancelled', now()->subMonths(2)->toDateTimeString());
        $open = $this->closedCandidacy('aperta@example.com', 'in_progress', now()->subMonths(9)->toDateTimeString());
        $hired = $this->closedCandidacy('inserito@example.com', 'cancelled', now()->subMonths(9)->toDateTimeString());
        $hired->update(['stipulated_at' => '2026-01-10']);

        $this->artisan('reclutamento:retention')->assertSuccessful();
        $this->assertSame('vecchio@example.com', $old->fresh()->email_private, 'senza --commit non cambia nulla');

        $this->artisan('reclutamento:retention', ['--commit' => true])->assertSuccessful();

        $this->assertNull(Fornitore::find($old->id), 'candidatura chiusa da 7 mesi: cancellato');
        $anonymized = Fornitore::withTrashed()->find($old->id);
        $this->assertNull($anonymized->email_private);
        $this->assertNull($anonymized->cf);
        $this->assertSame('CANDIDATO CANCELLATO', $anonymized->name);
        $this->assertNotNull(Fornitore::find($recent->id), 'chiusa da 2 mesi: si conserva');
        $this->assertNotNull(Fornitore::find($open->id), 'pratica in corso: non si tocca');
        $this->assertNotNull(Fornitore::find($hired->id), 'inserito in rete: non si tocca');
        $this->assertNull(ProcessInstance::where('subject_id', $old->id)->first()->incoming_email_id);
    }
}
