<?php

namespace Tests\Feature;

use App\Enums\Severity;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\ProcessTaskRaci;
use App\Services\ExternalCheckRunner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExternalCheckRecipientsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ProcessTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $process = Process::create(['name' => 'Compliance', 'code' => 'PRC-RCP-'.uniqid(), 'is_active' => true]);
        $this->task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Documentazione', 'ordine' => 1]);
    }

    private function role(string $name, string $raci, array $attributes = []): EmployeeType
    {
        $role = EmployeeType::create(['name' => $name] + $attributes);
        ProcessTaskRaci::create(['process_task_id' => $this->task->id, 'employee_type_id' => $role->id, 'raci_role' => $raci]);

        return $role;
    }

    public function test_who_holds_the_role_is_notified_with_the_registry_email_even_without_a_login_account(): void
    {
        $this->role('compliance', 'A');
        Employee::create(['name' => 'Maiello', 'email' => 'maiello@example.com', 'employee_roles' => ['compliance']]);

        $recipients = app(ExternalCheckRunner::class)->recipientsFor($this->task, Severity::Warning);

        $this->assertSame(['maiello@example.com'], $recipients->all());
        $this->assertSame([], app(ExternalCheckRunner::class)->recipientsFor($this->task, Severity::Regular)->all(), 'Il grado regular avvisa solo chi esegue (R).');
    }

    public function test_without_holders_the_office_email_is_used(): void
    {
        $this->role('segreteria amministrativa', 'R', ['email' => 'segreteria@example.com']);

        $this->assertSame(['segreteria@example.com'], app(ExternalCheckRunner::class)->recipientsFor($this->task, Severity::Regular)->all());
    }

    public function test_inactive_or_terminated_employees_are_not_notified(): void
    {
        $this->role('compliance', 'R');
        Employee::create(['name' => 'In servizio', 'email' => 'attivo@example.com', 'employee_roles' => ['compliance']]);
        Employee::create(['name' => 'Disattivato', 'email' => 'disattivato@example.com', 'employee_roles' => ['compliance'], 'is_active' => false]);
        Employee::create(['name' => 'Cessato', 'email' => 'cessato@example.com', 'employee_roles' => ['compliance'], 'termination_date' => now()->subDay()]);

        $this->assertSame(['attivo@example.com'], app(ExternalCheckRunner::class)->recipientsFor($this->task, Severity::Regular)->all());
    }
}
