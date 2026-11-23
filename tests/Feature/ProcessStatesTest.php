<?php

namespace Tests\Feature;

use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessState;
use App\Models\ProcessTask;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Company;
use Unico\Core\Models\IncomingEmail;

/** Stati applicativi dei processi (es. i 21 del reclutamento della rete) e riferimento della pratica all'email di unicopec da cui nasce. */
class ProcessStatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Process $process;

    /** @var array<int, ProcessTask> fasi per ordine (1, 2, 3) */
    private array $tasks = [];

    /** @var array<string, ProcessState> */
    private array $states = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->process = Process::create(['name' => 'Reclutamento', 'code' => 'PRC-ST-'.uniqid(), 'is_active' => true]);

        foreach ([1, 2, 3] as $ordine) {
            $this->tasks[$ordine] = ProcessTask::create(['process_id' => $this->process->id, 'name' => "Fase {$ordine}", 'ordine' => $ordine * 10]);
        }

        // 01 nella fase 2, 02 e 03 nella fase 3, 04 sospeso (eccezione) e 05 archiviato (finale) nella fase 3.
        foreach ([['01', 2, 1, []], ['02', 3, 2, []], ['03', 3, 3, []], ['04', 3, 4, ['is_exception' => true]], ['05', 3, 5, ['is_final' => true]]] as [$code, $fase, $ordine, $flags]) {
            $this->states[$code] = ProcessState::create(['process_id' => $this->process->id, 'process_task_id' => $this->tasks[$fase]->id, 'code' => $code, 'name' => "Stato {$code}", 'ordine' => $ordine] + $flags);
        }
    }

    private function newInstance(): ProcessInstance
    {
        return ProcessInstance::create(['process_id' => $this->process->id, 'current_task_id' => $this->tasks[1]->id, 'status' => 'in_progress']);
    }

    public function test_a_new_instance_starts_in_the_first_state_of_the_current_or_following_phase(): void
    {
        $this->assertSame('01', $this->newInstance()->processState->code);
    }

    public function test_the_state_advances_to_the_first_state_of_the_new_phase(): void
    {
        $instance = $this->newInstance();

        $instance->update(['current_task_id' => $this->tasks[2]->id]);
        $this->assertSame('01', $instance->fresh()->processState->code, 'la fase 2 ha già lo stato 01: resta');

        $instance->update(['current_task_id' => $this->tasks[3]->id]);
        $this->assertSame('02', $instance->fresh()->processState->code, 'passa al primo stato della fase 3, non a quelli finali o di eccezione');
    }

    public function test_final_and_exception_states_are_never_changed_automatically(): void
    {
        $instance = $this->newInstance();
        $instance->update(['process_state_id' => $this->states['04']->id]);

        $instance->update(['current_task_id' => $this->tasks[3]->id]);

        $this->assertSame('04', $instance->fresh()->processState->code);
    }

    public function test_a_process_without_states_leaves_the_field_empty(): void
    {
        $process = Process::create(['name' => 'Senza stati', 'code' => 'PRC-NS-'.uniqid(), 'is_active' => true]);
        $task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Unica', 'ordine' => 10]);

        $this->assertNull(ProcessInstance::create(['process_id' => $process->id, 'current_task_id' => $task->id, 'status' => 'in_progress'])->process_state_id);
    }

    public function test_the_instance_references_the_email_read_by_unicopec_it_comes_from(): void
    {
        $company = Company::create(['name' => 'Acme '.uniqid()]);
        $email = IncomingEmail::create([
            'company_id' => $company->id, 'message_id' => 'm-'.uniqid().'@x.test', 'from_email' => 'candidato@example.com',
            'subject' => 'Candidatura rete commerciale', 'body_text' => 'Buongiorno, mi candido.', 'received_at' => now(),
        ]);

        $instance = ProcessInstance::create(['process_id' => $this->process->id, 'current_task_id' => $this->tasks[1]->id, 'status' => 'in_progress', 'incoming_email_id' => $email->id]);

        $this->assertSame('Candidatura rete commerciale', $instance->fresh()->incomingEmail->subject);
    }
}
