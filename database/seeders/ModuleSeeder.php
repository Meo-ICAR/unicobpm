<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            ['code' => 'UNICOBPM', 'name' => 'UnicoBPM', 'description' => 'Gestione processi'],
            ['code' => 'UNICOOAM', 'name' => 'UnicoOAM', 'description' => 'Gestione compliance'],
            ['code' => 'PROFORMA', 'name' => 'Proforma', 'description' => 'Gestione contabilità'],
            ['code' => 'UNICOLOAN', 'name' => 'UnicoLoan', 'description' => 'Gestione finanziamenti'],
            ['code' => 'DASHBOARDAI', 'name' => 'DashboardAI', 'description' => 'Reporting direzionale'],
        ];

        foreach ($modules as $module) {
            Module::query()->updateOrCreate(['code' => $module['code']], $module);
        }
    }
}
