<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcessSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $processes = [
            [
                'id' => 1,
                'code' => 'PRC-AML',
                'name' => 'Processo Antiriciclaggio e Adeguata Verifica',
                'description' => 'Gestione integrata degli adempimenti KYC (Know Your Customer), valutazione del rischio e monitoraggio continuo.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'code' => 'PRC-TRANSP',
                'name' => 'Processo di Trasparenza Bancaria e Assicurativa',
                'description' => 'Verifica della conformità della documentazione precontrattuale, fogli informativi e tutele per il consumatore.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'code' => 'PRC-OAM',
                'name' => 'Processo di Vigilanza e Mantenimento Requisiti OAM',
                'description' => 'Verifiche periodiche sui requisiti professionali e di onorabilità per collaboratori e agenti.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('processes')->insert($processes);
    }
}
