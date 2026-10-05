<?php

namespace Database\Seeders;

use App\Models\Checklist;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $checklists = [
            [
                'code' => 'chk_aml_01',
                'name' => 'Antiriciclaggio (AML)',
                'description' => 'Checklist per gli adempimenti previsti dalla normativa Antiriciclaggio.',
                'is_active' => true,
            ],
            [
                'code' => 'chk_trasparenza_01',
                'name' => 'Trasparenza Bancaria',
                'description' => 'Checklist per la verifica della conformità sulle normative di trasparenza.',
                'is_active' => true,
            ],
            [
                'code' => 'chk_oam_01',
                'name' => 'Iscrizione OAM',
                'description' => 'Requisiti e documenti per il mantenimento dell\'iscrizione OAM.',
                'is_active' => true,
            ],
        ];

        foreach ($checklists as $checklist) {
            Checklist::updateOrCreate(['code' => $checklist['code']], $checklist);
        }
    }
}
