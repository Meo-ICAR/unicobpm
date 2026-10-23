<?php

use App\Models\EmployeeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le funzioni aziendali diventano ruoli (`employee_types`): ogni funzione passa al ruolo di EmployeeType::OFFICE_ROLES (creato se
 * manca) con i suoi dati d'ufficio; i membri ricevono il ruolo in `employee_roles` (anche il ruolo che avevano nella funzione) e il
 * responsabile diventa il responsabile del ruolo. Matrice RACI, passi e solleciti con riassegnazione puntano al ruolo.
 * Le tabelle delle funzioni restano (non più usate) per poter ricostruire il passaggio.
 */
return new class extends Migration
{
    /** @var array<int, int> id della funzione => id del ruolo */
    private array $roleOf = [];

    public function up(): void
    {
        $this->addRoleColumns();

        $core = DB::connection(config('unico-core.connection'));

        if ($core->getSchemaBuilder()->hasTable('business_functions')) {
            foreach ($core->table('business_functions')->orderBy('id')->get() as $function) {
                $this->roleOf[$function->id] = $this->roleFor($function)->id;
            }

            $this->moveMembers($core);
        }

        // I solleciti si convertono una volta sola: finché c'è la vecchia colonna il passaggio non è concluso.
        if (Schema::hasColumn('process_tasks', 'business_function_id')) {
            $this->moveReassignRules();
        }

        $this->fillRoleColumns();
        $this->dropFunctionColumns();

        // Ruolo scritto in modo diverso dal suo nome in employee_types.
        $this->renameRole($core, 'internal audit', 'audit');
    }

    public function down(): void
    {
        // I dati restano nei ruoli; le tabelle delle funzioni non sono state toccate.
    }

    private function addRoleColumns(): void
    {
        foreach (['process_task_raci', 'process_tasks'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'employee_type_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->unsignedBigInteger('employee_type_id')->nullable()->index()->after($table === 'process_tasks' ? 'process_id' : 'process_task_id')
                        ->comment('Ruolo (ufficio) responsabile');
                });
            }
        }
    }

    private function roleFor(object $function): EmployeeType
    {
        $role = EmployeeType::office($function->code, ['name' => $function->name]);

        foreach (['macro_area', 'type', 'description', 'outsourcable_status', 'managed_by_code', 'mission', 'responsibility', 'email'] as $field) {
            if (blank($role->{$field}) || ($field === 'outsourcable_status' && $role->{$field} === 'no')) {
                $role->{$field} = $function->{$field} ?? $role->{$field};
            }
        }

        $role->save();

        return $role;
    }

    private function moveMembers($core): void
    {
        if (! $core->getSchemaBuilder()->hasTable('business_function_members')) {
            return;
        }

        $tables = ['employee' => 'employees', 'App\\Models\\Employee' => 'employees', 'client' => 'clients', 'App\\Models\\Client' => 'clients'];
        $names = EmployeeType::query()->pluck('name', 'id');

        foreach ($core->table('business_function_members')->orderBy('id')->get() as $member) {
            $table = $tables[$member->member_type] ?? null;
            $roleId = $this->roleOf[$member->business_function_id] ?? null;

            if ($table === null || $roleId === null) {
                continue;
            }

            $row = $core->table($table)->where('id', $member->member_id)->first();

            if ($row === null) {
                continue;
            }

            $roles = collect(Arr::wrap(json_decode((string) $row->employee_roles, true)))
                ->push($names[$roleId] ?? null)
                ->push($member->employee_type_id ? ($names[$member->employee_type_id] ?? null) : null)
                ->filter()->unique()->values()->all();

            $core->table($table)->where('id', $member->member_id)->update(['employee_roles' => json_encode($roles, JSON_UNESCAPED_UNICODE)]);

            if ($member->is_manager) {
                EmployeeType::query()->whereKey($roleId)->update(['manager_type' => $table === 'employees' ? 'employee' : 'client', 'manager_id' => $member->member_id]);
            }
        }
    }

    private function fillRoleColumns(): void
    {
        foreach (['process_task_raci', 'process_tasks'] as $table) {
            if (! Schema::hasColumn($table, 'business_function_id')) {
                continue;
            }

            foreach ($this->roleOf as $functionId => $roleId) {
                DB::table($table)->where('business_function_id', $functionId)->whereNull('employee_type_id')->update(['employee_type_id' => $roleId]);
            }
        }
    }

    /** I solleciti con `reassign` indicavano la funzione per id: ora il ruolo. */
    private function moveReassignRules(): void
    {
        if (! Schema::hasColumn('process_tasks', 'escalation_rules')) {
            return;
        }

        DB::table('process_tasks')->whereNotNull('escalation_rules')->where('escalation_rules', 'like', '%target_business_function%')->orderBy('id')
            ->each(function (object $task): void {
                $rules = json_decode((string) $task->escalation_rules, true);

                if (! is_array($rules)) {
                    return;
                }

                foreach ($rules as &$rule) {
                    if (is_array($rule) && isset($rule['target_business_function']) && is_numeric($rule['target_business_function'])) {
                        $rule['target_business_function'] = $this->roleOf[(int) $rule['target_business_function']] ?? $rule['target_business_function'];
                    }
                }

                DB::table('process_tasks')->where('id', $task->id)->update(['escalation_rules' => json_encode($rules, JSON_UNESCAPED_UNICODE)]);
            });
    }

    private function dropFunctionColumns(): void
    {
        foreach (['process_task_raci', 'process_tasks'] as $table) {
            if (! Schema::hasColumn($table, 'business_function_id')) {
                continue;
            }

            foreach (DB::select('select constraint_name as c from information_schema.key_column_usage where table_schema = database() and table_name = ? and column_name = ? and referenced_table_name is not null', [$table, 'business_function_id']) as $fk) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($fk->c));
            }

            // Indici sulla vecchia colonna (anche l'unicità passo + funzione della matrice RACI): si tolgono prima della colonna.
            foreach (collect(Schema::getIndexes($table))->filter(fn (array $index): bool => ! $index['primary'] && in_array('business_function_id', $index['columns'], true)) as $index) {
                Schema::table($table, fn (Blueprint $blueprint) => $index['unique'] ? $blueprint->dropUnique($index['name']) : $blueprint->dropIndex($index['name']));
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('business_function_id'));
        }

        if (! collect(Schema::getIndexes('process_task_raci'))->contains('name', 'unique_raci_assignment')) {
            Schema::table('process_task_raci', fn (Blueprint $blueprint) => $blueprint->unique(['process_task_id', 'employee_type_id'], 'unique_raci_assignment'));
        }
    }

    private function renameRole($core, string $from, string $to): void
    {
        foreach (['employees', 'clients'] as $table) {
            if (! $core->getSchemaBuilder()->hasColumn($table, 'employee_roles')) {
                continue;
            }

            $core->table($table)->whereJsonContains('employee_roles', $from)->orderBy('id')->each(function (object $row) use ($core, $table, $from, $to): void {
                $roles = collect(json_decode((string) $row->employee_roles, true))->map(fn ($role) => $role === $from ? $to : $role)->unique()->values()->all();
                $core->table($table)->where('id', $row->id)->update(['employee_roles' => json_encode($roles, JSON_UNESCAPED_UNICODE)]);
            });
        }
    }
};
