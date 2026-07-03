<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChecklistItemSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::parse('2026-03-18 10:19:00');
        $updatedAt = Carbon::parse('2026-03-23 06:24:22');

        $items = [
            [
                'id' => 1,
                'checklist_id' => 1,
                'ordine' => '10',
                'name' => 'Documento Identità Valido',
                'item_code' => 'aml_doc_identita',
                'question' => 'Caricare copia del Documento di Identità e Codice Fiscale (o Tessera Sanitaria) in corso di validità del cliente/esecutore.',
                'description' => 'L\'identificazione deve avvenire preferibilmente in presenza.',
                'is_required' => 1,
                'attach_model' => 'principal',
                'n_documents' => 99,
                'depends_on_code' => null,
                'depends_on_value' => null,
                'dependency_type' => null,
                'process_task_code' => 'aml-check-completeness',
                'created_at' => $now,
                'updated_at' => $updatedAt,
            ],
            [
                'id' => 4,
                'checklist_id' => 1,
                'ordine' => '40',
                'name' => 'Visura Camerale',
                'item_code' => 'aml_visura',
                'question' => 'Caricare la Visura Camerale aggiornata (non antecedente a 6 mesi).',
                'description' => 'Necessaria per verificare i poteri di firma dell\'esecutore e l\'assetto societario.',
                'is_required' => 1,
                'attach_model' => 'principal',
                'n_documents' => 1,
                'depends_on_code' => 'aml_is_azienda',
                'depends_on_value' => '1',
                'dependency_type' => 'show_if',
                'process_task_code' => 'aml-check-completeness',
                'created_at' => $now,
                'updated_at' => $updatedAt,
            ],
            // ...Inserisci tutti gli altri items per le checklist OAM, Trasparenza ecc.
        ];

        DB::table('checklist_items')->insert($items);
    }
}
