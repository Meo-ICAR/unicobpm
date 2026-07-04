<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\DocumentType;
use App\Models\Process;
use Illuminate\Database\Seeder;

class BpmDesignSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Recupero riferimenti utili
        $complianceDept = BusinessFunction::where('code', 'COMPLIANCE')->first();
        $itDept = BusinessFunction::where('code', 'IT')->first();

        $ciType = DocumentType::where('code', 'CARTA_IDENTITA')->first();
        $visuraType = DocumentType::where('code', 'VISURA_CAMERALE')->first();

        // 2. Creazione del Modulo (Checklist) Antiriciclaggio
        $checklist = Checklist::firstOrCreate(['name' => 'Questionario Antiriciclaggio (AML)']);

        $checklist->items()->createMany([
            [
                'label' => 'Il soggetto è una Persona Politicamente Esposta (PEP)?',
                'type' => 'boolean',
                'is_knockout' => true, // Se true e si risponde 'Sì/Vero' in modo non conforme (gestito via logica), blocca tutto
            ],
            [
                'label' => 'Punteggio di rischio calcolato',
                'type' => 'number',
                'trigger_model' => 'App\Models\Agent',
                'trigger_field' => 'risk_score',
            ],
        ]);

        // 3. Creazione del Macro-Processo
        $process = Process::firstOrCreate(
            ['code' => 'AGENT_ONBOARDING', 'version' => 1],
            ['name' => 'Onboarding Nuovo Agente', 'is_active' => true]
        );

        // --- TASK 1: Raccolta Documenti Iniziali ---
        $task1 = $process->tasks()->create([
            'name' => 'Raccolta Documentazione',
            'ordine' => 10,
            'has_reminders' => true,
            'reminder_interval_days' => 2,
            'max_reminders' => 3,
        ]);

        // RACI per Task 1 (La Compliance supervisiona, non ci lavora attivamente)
        $task1->raci()->create(['business_function_id' => $complianceDept->id, 'raci_role' => 'A']); // Accountable

        $task1->items()->createMany([
            [
                'name' => 'Caricamento Carta Identità',
                'ordine' => 1,
                'action_type' => 'document_upload',
                'document_type_id' => $ciType->id,
                'is_required' => true,
            ],
            [
                'name' => 'Caricamento Visura Camerale',
                'ordine' => 2,
                'action_type' => 'document_upload',
                'document_type_id' => $visuraType->id,
                'is_required' => true,
            ],
        ]);

        // --- TASK 2: Controllo Compliance e Antiriciclaggio ---
        $task2 = $process->tasks()->create([
            'name' => 'Valutazione Compliance e AML',
            'ordine' => 20,
            'has_reminders' => false,
        ]);

        // RACI per Task 2 (L'Ufficio Compliance DEVE lavorarlo)
        $task2->raci()->create(['business_function_id' => $complianceDept->id, 'raci_role' => 'R']); // Responsible

        $task2->items()->createMany([
            [
                'name' => 'Compila Modulo AML',
                'ordine' => 1,
                'action_type' => 'fill_checklist',
                // N.B. In un'architettura completa, qui potresti aggiungere una colonna 'checklist_id' a process_task_items
                // oppure gestirlo tramite logica. Assumiamo che la action fill_checklist usi un campo di configurazione.
                'is_required' => true,
            ],
            [
                'name' => 'Note di valutazione (Opzionali)',
                'ordine' => 2,
                'action_type' => 'text_input',
                'is_required' => false,
            ],
        ]);

        // --- TASK 3: Creazione Utenze (System Task / Automazione) ---
        $task3 = $process->tasks()->create([
            'name' => 'Generazione Credenziali IT',
            'ordine' => 30,
        ]);

        // RACI per Task 3 (Dipartimento IT è responsabile, ma è un'azione automatica)
        $task3->raci()->create(['business_function_id' => $itDept->id, 'raci_role' => 'R']);

        $task3->items()->create([
            'name' => 'Provisioning Account Agente',
            'ordine' => 1,
            'action_type' => 'system_task',
            'handler_job' => 'App\Jobs\ProvisionAgentAccountJob',
            'is_required' => true,
        ]);
    }
}
