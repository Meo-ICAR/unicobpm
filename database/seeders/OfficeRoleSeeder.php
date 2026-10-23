<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use Illuminate\Database\Seeder;

/** Ruoli (uffici) usati dalle matrici RACI dimostrative. */
class OfficeRoleSeeder extends Seeder
{
    public function run(): void
    {
        $functions = [
            ['name' => 'Ufficio Compliance', 'code' => 'COMPLIANCE'],
            ['name' => 'Organizzazione e Risorse umane', 'code' => 'ORG-RU'],
            ['name' => 'Ufficio Legale', 'code' => 'LEGAL'],
            ['name' => 'Dipartimento IT', 'code' => 'IT'],
            // Ruoli referenziati dalla demo RACI su "Verifica Completezza Documentale AML"
            // (ProcessTaskSeeder / ProcessTaskRaciSeeder). Codici distinti da COMPLIANCE/IT sopra
            // perché rappresentano un secondo esempio di matrice RACI più articolato.
            ['name' => 'Back Office', 'code' => 'BUS-BO'],
            ['name' => 'Controllo Antiriciclaggio', 'code' => 'CTRL-AML'],
            ['name' => 'Rete Collaboratori', 'code' => 'BUS-RETE-EXT'],
            ['name' => 'Controllo Compliance', 'code' => 'CTRL-COMPL'],
        ];

        foreach ($functions as $function) {
            EmployeeType::office($function['code'], ['name' => $function['name']]);
        }
    }
}
