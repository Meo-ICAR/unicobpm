<?php

namespace Database\Seeders;

use App\Models\Process;
use Illuminate\Database\Seeder;

class ProcessSeeder extends Seeder
{
    public function run(): void
    {
        $processes = [
            [
                'code' => 'PRC-AML',
                'name' => 'Processo Antiriciclaggio e Adeguata Verifica',
                'description' => 'Gestione integrata degli adempimenti KYC (Know Your Customer), valutazione del rischio e monitoraggio continuo.',
                'is_active' => true,
            ],
            [
                'code' => 'PRC-TRANSP',
                'name' => 'Processo di Trasparenza Bancaria e Assicurativa',
                'description' => 'Verifica della conformità della documentazione precontrattuale, fogli informativi e tutele per il consumatore.',
                'is_active' => true,
            ],
            [
                'code' => 'PRC-OAM',
                'name' => 'Processo di Vigilanza e Mantenimento Requisiti OAM',
                'description' => 'Verifiche periodiche sui requisiti professionali e di onorabilità per collaboratori e agenti.',
                'is_active' => true,
            ],
        ];

        foreach ($processes as $process) {
            Process::updateOrCreate(['code' => $process['code']], $process);
        }
    }
}
