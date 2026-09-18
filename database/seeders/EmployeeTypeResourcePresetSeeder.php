<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\EmployeeType;
use App\Models\Resource;
use Illuminate\Database\Seeder;

class EmployeeTypeResourcePresetSeeder extends Seeder
{
    /**
     * Preset di accesso totale per il modulo UnicoOAM, ricavati dalle liste di
     * feature già definite in App\Enums\UserRole::features(). Mappa i ruoli
     * UserRole sugli EmployeeType equivalenti già censiti in employee_types,
     * cosi' un dipendente con quel ruolo ottiene lo stesso accesso previsto
     * per lo UserRole corrispondente.
     *
     * UserRole::ADMIN, SUPER_ADMIN e USER non hanno bisogno di preset: i primi
     * due bypassano già ogni controllo in resolvePianoAccess() STEP 0, mentre
     * USER corrisponde a "nessun profilo Employee/Fornitore/Client" e quindi è
     * già "fail open" allo STEP 2 (nessun employee_type_id da verificare).
     *
     * @var array<string, string>
     */
    private const ROLE_TO_EMPLOYEE_TYPE = [
        UserRole::QUALITY->value => 'qualita',
        UserRole::INSPECTOR->value => 'audit',
    ];

    private const APP_NAME = 'UnicoOAM';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::ROLE_TO_EMPLOYEE_TYPE as $roleValue => $employeeTypeName) {
            $userRole = UserRole::from($roleValue);

            $employeeType = EmployeeType::query()->where('name', $employeeTypeName)->first();

            if (! $employeeType) {
                continue;
            }

            $resourceIds = Resource::query()
                ->where('app_name', self::APP_NAME)
                ->whereIn('key', $userRole->features())
                ->pluck('id');

            foreach ($resourceIds as $resourceId) {
                $employeeType->resourcePresets()->firstOrCreate(['resource_id' => $resourceId]);
            }
        }
    }
}
