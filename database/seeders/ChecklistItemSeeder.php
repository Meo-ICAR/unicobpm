<?php

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Database\Seeder;

class ChecklistItemSeeder extends Seeder
{
    public function run(): void
    {
        $aml = Checklist::where('code', 'chk_aml_01')->first();

        if (! $aml) {
            $this->command?->warn('ChecklistItemSeeder: checklist "chk_aml_01" non trovata (esegui prima ChecklistSeeder), skip.');

            return;
        }

        $items = [
            [
                'item_code' => 'aml_doc_identita',
                'ordine' => 10,
                'name' => 'Documento Identità Valido',
                'question' => 'Caricare copia del Documento di Identità e Codice Fiscale (o Tessera Sanitaria) in corso di validità del cliente/esecutore.',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'aml_is_azienda',
                'ordine' => 20,
                'name' => 'Il soggetto è un\'azienda?',
                'question' => 'Il cliente è una persona giuridica (società) o una persona fisica?',
                'type' => 'boolean',
                'is_required' => true,
            ],
            [
                'item_code' => 'aml_visura',
                'ordine' => 40,
                'name' => 'Visura Camerale',
                'question' => 'Caricare la Visura Camerale aggiornata (non antecedente a 6 mesi).',
                'type' => 'boolean',
                'is_required' => true,
                'depends_on_code' => 'aml_is_azienda',
                'depends_on_value' => '1',
            ],
        ];

        foreach ($items as $item) {
            ChecklistItem::updateOrCreate(
                ['checklist_id' => $aml->id, 'item_code' => $item['item_code']],
                $item + ['checklist_id' => $aml->id]
            );
        }
    }
}
