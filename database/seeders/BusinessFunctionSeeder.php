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
            // Funzioni referenziate dalla demo RACI su "Verifica Completezza Documentale AML"
            // (ProcessTaskSeeder / ProcessTaskRaciSeeder). Codici distinti da COMPLIANCE/IT sopra
            // perché rappresentano un secondo esempio di matrice RACI più articolato.
            ['name' => 'Back Office', 'code' => 'BUS-BO'],
            ['name' => 'Controllo Antiriciclaggio', 'code' => 'CTRL-AML'],
            ['name' => 'Rete Agenti Esterna', 'code' => 'BUS-RETE-EXT'],
            ['name' => 'Controllo Compliance', 'code' => 'CTRL-COMPL'],
        ];

        foreach ($functions as $function) {
            BusinessFunction::updateOrCreate(['code' => $function['code']], $function);
        }
    }
}
