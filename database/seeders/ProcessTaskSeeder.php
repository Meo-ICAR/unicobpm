<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcessTaskSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $tasks = [
            [
                'id' => 1,
                'code' => 'aml-check-completeness',
                'name' => 'Verifica Completezza Documentale AML',
                'description' => 'Controllo preliminare sui documenti di identità, visure e moduli di adeguata verifica.',
                'process_id' => 1,
                'business_function_id' => 10, // Collegato a CTRL-AML
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'code' => 'aml-risk-evaluation',
                'name' => 'Valutazione Rischio Cliente',
                'description' => 'Calcolo e attribuzione della fascia di rischio per l\'adeguata verifica (Semplificata, Ordinaria, Rafforzata).',
                'process_id' => 1,
                'business_function_id' => 10, // Collegato a CTRL-AML
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('process_tasks')->insert($tasks);
    }
}
