<?php

namespace Tests\Feature;

use App\Jobs\ProvisionAgentAccountJob;
use App\Models\Fornitore;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProvisionAgentAccountJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function instanceFor(Fornitore $fornitore): ProcessInstance
    {
        $process = Process::query()->create(['name' => 'Onboarding Nuovo Agente', 'code' => 'AGENT_ONBOARDING-'.uniqid(), 'target_model' => 'fornitore']);

        return ProcessInstance::query()->create(['process_id' => $process->id, 'subject_type' => 'fornitore', 'subject_id' => $fornitore->getKey(), 'status' => 'in_progress']);
    }

    public function test_the_agent_gets_a_portal_account_linked_to_the_producer_and_a_second_run_changes_nothing(): void
    {
        $fornitore = Fornitore::query()->create(['name' => 'Rossi Mario', 'email' => 'Mario.Rossi@Example.com']);
        $instance = $this->instanceFor($fornitore);

        (new ProvisionAgentAccountJob($instance->id))->handle();
        (new ProvisionAgentAccountJob($instance->id))->handle();

        $user = User::query()->where('email', 'mario.rossi@example.com')->sole();
        $this->assertSame('fornitore', $user->role);
        $this->assertSame($user->id, $fornitore->fresh()->user_id);
    }

    public function test_a_producer_without_a_valid_email_stops_the_step(): void
    {
        $instance = $this->instanceFor(Fornitore::query()->create(['name' => 'Bianchi', 'email' => null]));

        $this->expectException(RuntimeException::class);

        (new ProvisionAgentAccountJob($instance->id))->handle();
    }
}
