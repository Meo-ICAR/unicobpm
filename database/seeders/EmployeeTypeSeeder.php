<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use Illuminate\Database\Seeder;

class EmployeeTypeSeeder extends Seeder
{
    /**
     * Ruoli RBAC di suite: si seminano solo da unicobpm (tabella core employee_types).
     */
    public function run(): void
    {
        $employeeTypes = [
            ['id' => 1,  'name' => 'dipendente',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 2,  'name' => 'cda',            'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 3,  'name' => 'istruttore',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 4,  'name' => 'SOS',            'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 5,  'name' => 'audit',          'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 6,  'name' => 'compliance',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 7,  'name' => 'segretaria',     'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 8,  'name' => 'amministrativo',  'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 9,  'name' => 'commerciale',    'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 10, 'name' => 'qualita',        'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            // Dall'organigramma (Relazione sui requisiti organizzativi, luglio 2026)
            ['id' => 11, 'name' => 'amministratore delegato', 'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 12, 'name' => 'antiriciclaggio', 'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 13, 'name' => 'it',              'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 14, 'name' => 'risorse umane',   'icon' => null, 'companytype' => 'FINANCE', 'is_external' => false],
            ['id' => 15, 'name' => 'dpo',             'icon' => null, 'companytype' => 'FINANCE', 'is_external' => true],
            ['id' => 16, 'name' => 'legale',          'icon' => null, 'companytype' => 'FINANCE', 'is_external' => true],
        ];

        foreach ($employeeTypes as $type) {
            EmployeeType::updateOrCreate(
                ['id' => $type['id']],
                $type
            );
        }
    }
}
