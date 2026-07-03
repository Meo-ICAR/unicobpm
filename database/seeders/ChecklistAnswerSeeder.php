<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChecklistAnswerSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2026-03-23 06:03:41');

        // Nel tuo dump SQL ci sono molteplici record inseriti per le risposte.
        // Qui inserisco un sottoinsieme rappresentativo per popolare la tabella.
        // Assicurati che checklist_submission_id esista (puoi creare un submission fittizio se serve).

        $answers = [
            [
                'id' => 83,
                'checklist_submission_id' => 62,
                'checklist_item_id' => 11,
                'value_text' => 'A',
                'value_boolean' => null,
                'value_array' => null,
                'annotation' => null,
                'attached_model_type' => null,
                'attached_model_id' => null,
                'company_id' => null, // Sostituisci con l'UUID aziendale se necessario
                'ordine' => 0,
                'n_documents' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 84,
                'checklist_submission_id' => 62,
                'checklist_item_id' => 5,
                'value_text' => 'C',
                'value_boolean' => null,
                'value_array' => null,
                'annotation' => null,
                'attached_model_type' => null,
                'attached_model_id' => null,
                'company_id' => null,
                'ordine' => 0,
                'n_documents' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 85,
                'checklist_submission_id' => 62,
                'checklist_item_id' => 3,
                'value_text' => 'I',
                'value_boolean' => null,
                'value_array' => null,
                'annotation' => null,
                'attached_model_type' => null,
                'attached_model_id' => null,
                'company_id' => null,
                'ordine' => 0,
                'n_documents' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('checklist_answers')->insert($answers);
    }
}
