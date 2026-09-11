<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Process;
use App\Models\ProcessTask;
use Illuminate\Database\Seeder;

class ProcessTaskSeeder extends Seeder
{
    public function run(): void
    {
        $process = Process::where('code', 'PRC-AML')->first();
        $ctrlAml = BusinessFunction::where('code', 'CTRL-AML')->first();

        if (! $process || ! $ctrlAml) {
            $this->command?->warn('ProcessTaskSeeder: prerequisiti mancanti (ProcessSeeder/BusinessFunctionSeeder), skip.');

            return;
        }

        $tasks = [
            [
                'code' => 'aml-check-completeness',
                'name' => 'Verifica Completezza Documentale AML',
                'description' => 'Controllo preliminare sui documenti di identità, visure e moduli di adeguata verifica.',
                'ordine' => 10,
                'business_function_id' => $ctrlAml->id,
            ],
            [
                'code' => 'aml-risk-evaluation',
                'name' => 'Valutazione Rischio Cliente',
                'description' => 'Calcolo e attribuzione della fascia di rischio per l\'adeguata verifica (Semplificata, Ordinaria, Rafforzata).',
                'ordine' => 20,
                'business_function_id' => $ctrlAml->id,
            ],
        ];

        foreach ($tasks as $task) {
            ProcessTask::updateOrCreate(
                ['process_id' => $process->id, 'code' => $task['code']],
                $task + ['process_id' => $process->id]
            );
        }
    }
}
