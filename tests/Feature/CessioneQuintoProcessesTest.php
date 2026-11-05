<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTrigger;
use Database\Seeders\CessioneQuintoProcessesSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CessioneQuintoProcessesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = ['X-Api-Key' => 'chiave-eventi'];

    private const CODES = ['PROC_ORIGINATION', 'PROC_ISTRUTTORIA', 'PROC_DELIBERA', 'PROC_NOTIFICA', 'PROC_BENESTARE', 'PROC_LIQUIDAZIONE'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['unico-core.bridge.api_key' => 'chiave-eventi', 'mail.default' => 'array']);
        $this->seed(CessioneQuintoProcessesSeeder::class);
    }

    private function statoCambiato(string $stato, string $tipo = 'Cessione', string $id = '1'): array
    {
        return [
            'event_id' => "pratica.stato_cambiato:{$id}:{$stato}", 'event' => 'pratica.stato_cambiato', 'source' => 'unicofinance', 'subject_ref' => "P-{$id}",
            'data' => ['stato' => $stato, 'tipo_finanziamento' => $tipo, 'cliente_nome' => 'Mario Rossi', 'cliente_email' => 'mario@example.com'],
        ];
    }

    public function test_the_seeder_creates_six_processes_with_their_steps_and_is_idempotent(): void
    {
        $this->seed(CessioneQuintoProcessesSeeder::class);

        $this->assertSame(6, Process::whereIn('code', self::CODES)->count());
        $steps = Process::whereIn('code', self::CODES)->orderBy('id')->get()->mapWithKeys(fn (Process $p) => [$p->code => $p->tasks()->count()]);
        $this->assertSame(
            ['PROC_ORIGINATION' => 4, 'PROC_ISTRUTTORIA' => 4, 'PROC_DELIBERA' => 4, 'PROC_NOTIFICA' => 3, 'PROC_BENESTARE' => 3, 'PROC_LIQUIDAZIONE' => 5],
            $steps->all()
        );
        $this->assertSame(6, ProcessTrigger::where('model_class', 'external')->whereIn('external_event', ['lead.creato', 'pratica.stato_cambiato'])->count());
        $this->assertSame(1, EmployeeType::where('code', 'ATC-ENTE')->count());
        $this->assertTrue((bool) EmployeeType::where('code', 'PARTNER-BANCA')->value('is_external'));
        $this->assertSame(4, EmailTemplate::whereIn('code', ['ORIG_PROPOSTA', 'DELI_TRASMISSIONE', 'NOTI_PEC_ATC', 'LIQI_CONFERMA'])->count());
    }

    public function test_every_step_has_one_row_per_role_and_a_responsible_and_escalation_rules(): void
    {
        foreach (Process::whereIn('code', self::CODES)->get() as $process) {
            foreach ($process->tasks as $task) {
                $roles = $task->raciAssignments()->pluck('employee_type_id');
                $this->assertSame($roles->count(), $roles->unique()->count(), $task->code);
                $this->assertTrue($task->raciAssignments()->where('raci_role', 'R')->exists(), $task->code);
                $this->assertCount(2, $task->escalation_rules, $task->code);
            }
        }

        $compliance = EmployeeType::where('code', 'COMPLIANCE')->value('id');
        $istruttoria = Process::where('code', 'PROC_ISTRUTTORIA')->firstOrFail()->tasks()->where('ordine', 10)->firstOrFail();
        $this->assertSame(['reassign', $compliance], [$istruttoria->escalation_rules[1]['action'], $istruttoria->escalation_rules[1]['target_business_function']]);
    }

    public function test_a_practice_entering_the_state_starts_only_the_matching_process(): void
    {
        $this->postJson('/api/events', $this->statoCambiato(CessioneQuintoProcessesSeeder::STATI['PROC_ISTRUTTORIA']), self::KEY)
            ->assertStatus(202)->assertJson(['status' => 'processed', 'started' => 1]);

        $instance = ProcessInstance::sole();
        $this->assertSame('PROC_ISTRUTTORIA', $instance->process->code);
        $this->assertSame('unicofinance:P-1', $instance->external_ref);
        $this->assertSame('Verifica identità e firme', $instance->currentTask->name);
    }

    public function test_other_loan_types_and_other_states_start_nothing(): void
    {
        $this->postJson('/api/events', $this->statoCambiato(CessioneQuintoProcessesSeeder::STATI['PROC_DELIBERA'], 'Mutuo', '2'), self::KEY)->assertStatus(202)->assertJson(['started' => 0]);
        $this->postJson('/api/events', $this->statoCambiato('BOZZA', 'Cessione', '3'), self::KEY)->assertStatus(202)->assertJson(['started' => 0]);

        $this->assertSame(0, ProcessInstance::count());
    }

    public function test_a_new_lead_starts_the_origination_and_the_proposal_goes_to_the_client(): void
    {
        $event = ['event_id' => 'lead.creato:9', 'event' => 'lead.creato', 'source' => 'unicofinance', 'subject_ref' => 'L-9',
            'data' => ['cliente_nome' => 'Mario Rossi', 'cliente_email' => 'mario@example.com']];

        $this->postJson('/api/events', $event, self::KEY)->assertStatus(202)->assertJson(['started' => 1]);

        $this->assertSame('PROC_ORIGINATION', ProcessInstance::sole()->process->code);
        $this->assertSame(0, Mail::mailer('array')->getSymfonyTransport()->messages()->count(), 'la proposta parte solo all\'ultimo passo');
    }
}
