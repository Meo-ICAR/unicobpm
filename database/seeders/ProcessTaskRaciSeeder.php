<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\ProcessTask;
use Illuminate\Database\Seeder;

class ProcessTaskRaciSeeder extends Seeder
{
    public function run(): void
    {
        $task = ProcessTask::where('code', 'aml-check-completeness')->first();

        if (! $task) {
            $this->command?->warn('ProcessTaskRaciSeeder: task "aml-check-completeness" non trovato (esegui prima ProcessTaskSeeder), skip.');

            return;
        }

        // R (Responsible): esecutore materiale (Back Office)
        // A (Accountable): chi ne risponde alla fine (Controllo Antiriciclaggio)
        // C (Consulted): chi viene consultato (Rete Agenti Esterna)
        // I (Informed): chi viene informato (Controllo Compliance)
        $assignments = [
            ['code' => 'BUS-BO', 'raci_role' => 'R', 'notes' => 'Esegue materialmente la raccolta e il controllo documentale di base'],
            ['code' => 'CTRL-AML', 'raci_role' => 'A', 'notes' => 'Approva in via definitiva l\'adeguatezza del fascicolo'],
            ['code' => 'BUS-RETE-EXT', 'raci_role' => 'C', 'notes' => 'Fornisce chiarimenti sul cliente in caso di documenti mancanti'],
            ['code' => 'CTRL-COMPL', 'raci_role' => 'I', 'notes' => 'Viene informata tramite report mensile sulle anomalie documentali'],
        ];

        foreach ($assignments as $assignment) {
            $businessFunction = BusinessFunction::where('code', $assignment['code'])->first();

            if (! $businessFunction) {
                continue;
            }

            $task->raciAssignments()->updateOrCreate(
                ['business_function_id' => $businessFunction->id],
                ['raci_role' => $assignment['raci_role'], 'notes' => $assignment['notes']]
            );
        }
    }
}
