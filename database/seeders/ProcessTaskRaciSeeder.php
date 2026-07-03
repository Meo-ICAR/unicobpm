<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcessTaskRaciSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Esempio: Per il task "Verifica Completezza Documentale AML" (id 1)
        // R (Responsible): Esecutore materiale (es. Back Office)
        // A (Accountable): Chi ne risponde alla fine (es. Responsabile AML)
        // C (Consulted): Chi viene consultato (es. Compliance)
        // I (Informed): Chi viene informato (es. Direzione)

        $raciAssignments = [
            [
                'process_task_id' => 1, // Verifica Completezza Documentale AML
                'business_function_id' => 5, // BUS-BO (Back Office)
                'raci_role' => 'R',
                'notes' => 'Esegue materialmente la raccolta e il controllo documentale di base',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'process_task_id' => 1,
                'business_function_id' => 10, // CTRL-AML (Antiriciclaggio)
                'raci_role' => 'A',
                'notes' => 'Approva in via definitiva l\'adeguatezza del fascicolo',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'process_task_id' => 1,
                'business_function_id' => 4, // BUS-RETE-EXT (Agenti)
                'raci_role' => 'C',
                'notes' => 'Fornisce chiarimenti sul cliente in caso di documenti mancanti',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'process_task_id' => 1,
                'business_function_id' => 9, // CTRL-COMPL (Compliance)
                'raci_role' => 'I',
                'notes' => 'Viene informata tramite report mensile sulle anomalie documentali',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('process_task_raci')->insert($raciAssignments);
    }
}
