<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $checklists = [
            [
                'id' => 1,
                'name' => 'Antiriciclaggio (AML)',
                'code' => 'chk_aml_01',
                'description' => 'Checklist per gli adempimenti previsti dalla normativa Antiriciclaggio.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Trasparenza Bancaria',
                'code' => 'chk_trasparenza_01',
                'description' => 'Checklist per la verifica della conformità sulle normative di trasparenza.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Iscrizione OAM',
                'code' => 'chk_oam_01',
                'description' => 'Requisiti e documenti per il mantenimento dell\'iscrizione OAM.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('checklists')->insert($checklists);
    }
}
