<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Carta d\'Identità', 'code' => 'CARTA_IDENTITA'],
            ['name' => 'Codice Fiscale', 'code' => 'CODICE_FISCALE'],
            ['name' => 'Visura Camerale', 'code' => 'VISURA_CAMERALE'],
            ['name' => 'Contratto Firmato', 'code' => 'CONTRATTO_FIRMATO'],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
