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
            ['name' => 'Busta Paga / CU', 'code' => 'BUSTA_PAGA'],
            ['name' => 'Estratto Conto Bancario', 'code' => 'ESTRATTO_CONTO'],
            ['name' => 'Delibera Istituto Finanziatore', 'code' => 'DELIBERA_ISTITUTO'],
            ['name' => 'Attestato Formazione OAM', 'code' => 'ATTESTATO_FORMAZIONE'],
            ['name' => 'Polizza RC Professionale', 'code' => 'POLIZZA_RC_PROFESSIONALE'],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
