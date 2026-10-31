<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'hassistosrl@gmail.com',
                //   'is_super_admin' => true,
            ],
            [
                'name' => 'Sergio Bracale',
                'email' => 'sergio.bracale@races.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Mario',
                'email' => 'mario@globaladvisory.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Roberto Perna',
                'email' => 'segreteria@races.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Eustachio Allegretti',
                'email' => 'eustachio.allegretti@races.it',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Michele Ferri',
                'email' => 'avvferrimichele@gmail.com',
                //  'is_super_admin' => false,
            ],
            [
                'name' => 'Framcesco Maiello',
                'email' => 'avvocatofrancescomaiello@gmail.com',
                //  'is_super_admin' => false,
            ],
        ];

        foreach ($users as $userData) {
            if (! User::where('email', $userData['email'])->exists()) {
                User::factory()->create(array_merge($userData, [
                    'password' => Hash::make('password'),
                ]));
            }
        }

        $this->call([
            // 1. Anagrafiche di base (nessuna Foreign Key)
            DocumentTypeSeeder::class,
            MergeDuplicateDocumentTypesSeeder::class, // Unisce Polizza/Visura/Relazione audit e code TRASPARENZA
            TaskSeeder::class,
            TaskDocumentTypeSeeder::class, // Dipende da DocumentTypeSeeder e TaskSeeder
            OfficeRoleSeeder::class,
            OrganigrammaSeeder::class,
            ChecklistSeeder::class,
            ProcessSeeder::class,

            // 2. Dipendenze di primo livello
            ProcessTaskSeeder::class, // Necessita di Process e dei ruoli (uffici)
            ChecklistItemSeeder::class, // Necessita di Checklist

            // 3. Dipendenze di secondo livello
            ProcessTaskRaciSeeder::class, // Necessita di ProcessTask e dei ruoli (uffici)

            // 4. Processi completi (task + RACI + azioni) tipici di un mediatore creditizio
            BpmDesignSeeder::class, // Onboarding Nuovo Agente
            CreditBrokerProcessesSeeder::class, // AML, Trasparenza, OAM, Istruttoria Finanziamento
            ScadenziarioCogeProcessSeeder::class, // Scadenziario COGE (check Proforma con severity + RACI)
            ComplianceDocumentazioneProcessSeeder::class, // Compliance (documenti scaduti da UnicoOAM)
            ProcessEmailTemplateSeeder::class, // Solleciti di presa in carico (TASK_ESCALATION)
            WhistleblowingProcessSeeder::class, // Segnalazioni da UnicoWhistle
            RettificaIbanProcessSeeder::class, // Cambio IBAN comunicato per email (da UnicoPEC)
            ValidazioneFatturaProformaProcessSeeder::class, // Fattura ricevuta per email, da confrontare con il proforma
            IbanAllineamentoProcessSeeder::class, // IBAN agente variato (da UnicoPEC): avviso al contabile finché non è allineato
            ProcedureAziendaliSeeder::class, // Procedure aziendali dalla Relazione sui requisiti organizzativi (rete, pratiche, governance)

            // 5. Preset di accesso per ruolo (richiede employee_types e resources già censiti)
            EmployeeTypeSeeder::class,
            EmployeeTypeResourcePresetSeeder::class,
        ]);
    }
}
