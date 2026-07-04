<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use Illuminate\Database\Seeder;

class BusinessFunctionSeeder extends Seeder
{
    public function run(): void
    {
        $functions = [
            ['name' => 'Ufficio Compliance', 'code' => 'COMPLIANCE'],
            ['name' => 'Risorse Umane', 'code' => 'HR'],
            ['name' => 'Ufficio Legale', 'code' => 'LEGAL'],
            ['name' => 'Dipartimento IT', 'code' => 'IT'],
        ];

        foreach ($functions as $function) {
            BusinessFunction::updateOrCreate(['code' => $function['code']], $function);
        }
    }
}
