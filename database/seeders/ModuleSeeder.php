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
            ['code' => 'UNICOCOGE', 'name' => 'UnicoCoge', 'description' => 'Gestione contabilità'],
            ['code' => 'UNICOFINANCE', 'name' => 'UnicoFinance', 'description' => 'Istruttoria delle pratiche'],
            ['code' => 'DASHBOARDAI', 'name' => 'DashboardAI', 'description' => 'Reporting direzionale'],
        ];

        foreach ($modules as $module) {
            Module::query()->updateOrCreate(['code' => $module['code']], $module);
        }
    }
}
