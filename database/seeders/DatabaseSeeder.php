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
            TaskSeeder::class,
            TaskDocumentTypeSeeder::class, // Dipende da DocumentTypeSeeder e TaskSeeder
            BusinessFunctionSeeder::class,
            ChecklistSeeder::class,
            ProcessSeeder::class,

            // 2. Dipendenze di primo livello
            ProcessTaskSeeder::class, // Necessita di Process e BusinessFunction
            ChecklistItemSeeder::class, // Necessita di Checklist

            // 3. Dipendenze di secondo livello
            ProcessTaskRaciSeeder::class, // Necessita di ProcessTask e BusinessFunction

            // 4. Processi completi (task + RACI + azioni) tipici di un mediatore creditizio
            BpmDesignSeeder::class, // Onboarding Nuovo Agente
            CreditBrokerProcessesSeeder::class, // AML, Trasparenza, OAM, Istruttoria Finanziamento
        ]);
    }
}
