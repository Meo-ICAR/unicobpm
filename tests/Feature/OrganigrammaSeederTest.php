<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessTaskRaci;
use Database\Seeders\OrganigrammaSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrganigrammaSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_offices_of_the_chart_are_roles_with_their_hierarchy_and_the_seeder_is_idempotent(): void
    {
        $this->seed(OrganigrammaSeeder::class);
        $this->seed(OrganigrammaSeeder::class);

        foreach (OrganigrammaSeeder::ORGANIGRAMMA as $code => [$name, $office, , , $outsourcable, $managedBy]) {
            $role = EmployeeType::query()->where('code', $code)->sole();
            $this->assertSame($name, $role->name);
            $this->assertSame($office, $role->description);
            $this->assertSame($outsourcable, $role->outsourcable_status);
            $this->assertSame($managedBy, $role->managed_by_code);
        }

        $this->assertTrue(EmployeeType::query()->where('code', 'COMPLIANCE')->sole()->isOutsourcable());
        $this->assertFalse(EmployeeType::query()->where('code', 'BUS-BO')->sole()->isOutsourcable());
    }

    public function test_the_old_hr_role_is_merged_and_renamed_roles_follow_their_holders(): void
    {
        $hr = EmployeeType::query()->create(['name' => 'risorse umane', 'code' => 'HR']);
        $secretary = EmployeeType::query()->create(['name' => 'segretaria', 'code' => 'SEGRETERIA']);
        $employee = Employee::query()->create(['name' => 'Bianchi', 'email' => 'bianchi@example.com', 'employee_roles' => ['risorse umane', 'segretaria']]);
        $task = Process::query()->create(['name' => 'Selezione', 'code' => 'PRC-T'])->tasks()->create(['name' => 'Colloquio', 'ordine' => 10]);
        ProcessTaskRaci::query()->create(['process_task_id' => $task->id, 'employee_type_id' => $hr->id, 'raci_role' => 'R']);

        $this->seed(OrganigrammaSeeder::class);

        $orgRu = EmployeeType::query()->where('code', 'ORG-RU')->sole();
        $this->assertNull(EmployeeType::query()->find($hr->id));
        $this->assertSame($orgRu->id, ProcessTaskRaci::query()->where('process_task_id', $task->id)->value('employee_type_id'));
        $this->assertSame('segreteria amministrativa', $secretary->fresh()->name);
        $this->assertEqualsCanonicalizing(['organizzazione e risorse umane', 'segreteria amministrativa'], $employee->fresh()->employee_roles);
    }
}
