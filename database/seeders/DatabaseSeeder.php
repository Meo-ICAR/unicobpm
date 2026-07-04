<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
                $user = User::factory()->create($userData);
                $user->save();
            }
        }

        $this->call([
            DocumentTypeSeeder::class,
            BusinessFunctionSeeder::class,
            BpmDesignSeeder::class,

            // Opzionale: aggiungi qui un eventuale UserSeeder
            // per creare gli amministratori di test

            /*
            // 1. Anagrafiche di base (nessuna Foreign Key)
            //  CompanySeeder::class,
            ChecklistSeeder::class,
            ProcessSeeder::class,
            BusinessFunctionSeeder::class,

            // 2. Dipendenze di primo livello
            ProcessTaskSeeder::class,          // Necessita di Processes e BusinessFunctions

            // 3. Dipendenze di secondo livello
            ProcessTaskRaciSeeder::class,      // Necessita di ProcessTasks e BusinessFunctions
            ChecklistItemSeeder::class,        // Necessita di Checklists, ProcessTasks e BusinessFunctions

            // 4. Dipendenze finali (Tabelle pivot o di risposta)
            // ChecklistAnswerSeeder::class,      // Necessita di ChecklistAnswers e ChecklistItems - DISABILITATO fino a migration completa
            */
        ]);
    }
}
