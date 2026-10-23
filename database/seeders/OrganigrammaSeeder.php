<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use Unico\Core\Seeders\OrganigrammaSeeder as CoreOrganigrammaSeeder;

/**
 * Organigramma del core più, nei processi, l'unione dei ruoli che erano lo stesso ufficio con un altro codice: chi li ricopre,
 * la matrice RACI e i passi passano al ruolo dell'organigramma.
 */
class OrganigrammaSeeder extends CoreOrganigrammaSeeder
{
    /** Vecchio codice => codice dell'organigramma. */
    private const MERGED = ['HR' => 'ORG-RU'];

    public function run(): void
    {
        parent::run();

        foreach (self::MERGED as $old => $new) {
            $from = EmployeeType::query()->where('code', $old)->first();
            $into = EmployeeType::query()->where('code', $new)->first();

            if ($from && $into) {
                $from->mergeInto($into);
            }
        }
    }
}
