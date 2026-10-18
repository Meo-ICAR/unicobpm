<?php

namespace Tests\Feature;

use App\Models\Module;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use Unico\Core\Models\Company;
use Unico\Core\Models\CompanyModule;

class MoveUnicoloanModuleToUnicofinanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_18_170000_move_unicoloan_module_to_unicofinance.php'))->up();
    }

    public function test_without_unicofinance_the_unicoloan_module_is_renamed(): void
    {
        Module::query()->where('code', 'UNICOFINANCE')->delete();
        $loan = Module::create(['code' => 'UNICOLOAN', 'name' => 'UnicoLoan']);
        $company = Company::create(['name' => 'Acme', 'vat_number' => '01234567890']);
        $row = CompanyModule::create(['company_id' => $company->id, 'module_id' => $loan->id, 'plan_type' => 'FULL']);

        $this->migrate();

        $this->assertSame('UNICOFINANCE', $loan->fresh()->code);
        $this->assertSame($loan->id, $row->fresh()->module_id);
    }

    public function test_companies_move_to_unicofinance_keeping_plan_and_existing_rows(): void
    {
        $loan = Module::query()->firstOrCreate(['code' => 'UNICOLOAN'], ['name' => 'UnicoLoan']);
        $finance = Module::query()->firstOrCreate(['code' => 'UNICOFINANCE'], ['name' => 'UnicoFinance']);
        $onlyLoan = Company::create(['name' => 'Solo loan', 'vat_number' => '01234567891']);
        $both = Company::create(['name' => 'Entrambi', 'vat_number' => '01234567892']);
        $moved = CompanyModule::create(['company_id' => $onlyLoan->id, 'module_id' => $loan->id, 'plan_type' => 'MEDIUM', 'monthly_cost' => 90]);
        $kept = CompanyModule::create(['company_id' => $both->id, 'module_id' => $loan->id, 'plan_type' => 'BASE']);
        CompanyModule::create(['company_id' => $both->id, 'module_id' => $finance->id, 'plan_type' => 'FULL']);

        $this->migrate();

        $this->assertSame($finance->id, $moved->fresh()->module_id);
        $this->assertSame('MEDIUM', $moved->fresh()->plan_type->value);
        $this->assertSame($loan->id, $kept->fresh()->module_id);
        $this->assertFalse($loan->fresh()->is_active);
    }
}
