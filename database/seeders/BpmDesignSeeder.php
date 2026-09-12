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

        $checklist->items()->updateOrCreate(
            ['checklist_id' => $checklist->id, 'item_code' => 'agent_is_pep'],
            [
                'label' => 'Il soggetto è una Persona Politicamente Esposta (PEP)?',
                'type' => 'boolean',
                'is_knockout' => true, // Se true e si risponde 'Sì/Vero' in modo non conforme (gestito via logica), blocca tutto
            ]
        );

        $checklist->items()->updateOrCreate(
            ['checklist_id' => $checklist->id, 'item_code' => 'agent_risk_score'],
            [
                'label' => 'Punteggio di rischio calcolato',
                'type' => 'number',
                'trigger_field' => 'risk_score',
            ]
        );

        // 3. Creazione del Macro-Processo
        $process = Process::firstOrCreate(
            ['code' => 'AGENT_ONBOARDING', 'version' => 1],
            ['name' => 'Onboarding Nuovo Agente', 'is_active' => true]
        );

        // Un agente con 'stipulated_at' già valorizzato ha già un mandato attivo e non
        // necessita di un nuovo onboarding; al completamento, valorizziamo quel campo.
        $process->update([
            'target_model' => 'fornitore',
            'exclude_field' => 'stipulated_at',
            'exclude_state' => 'filled',
            'completion_write_field' => 'stipulated_at',
            'completion_write_value' => 'now',
            'completion_write_app' => 'unicoloan',
        ]);

        // --- TASK 1: Raccolta Documenti Iniziali ---
        $task1 = $process->tasks()->updateOrCreate(
            ['process_id' => $process->id, 'ordine' => 10],
            [
                'name' => 'Raccolta Documentazione',
                'has_reminders' => true,
                'reminder_interval_days' => 2,
                'max_reminders' => 3,
            ]
        );

        // RACI per Task 1 (La Compliance supervisiona, non ci lavora attivamente)
        if ($complianceDept) {
            $task1->raciAssignments()->updateOrCreate(
                ['business_function_id' => $complianceDept->id],
                ['raci_role' => 'A'] // Accountable
            );
        }

        $task1->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task1->id, 'ordine' => 1],
            [
                'name' => 'Caricamento Carta Identità',
                'action_type' => 'document_upload',
                'document_type_id' => $ciType?->id,
                'is_required' => true,
            ]
        );

        $task1->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task1->id, 'ordine' => 2],
            [
                'name' => 'Caricamento Visura Camerale',
                'action_type' => 'document_upload',
                'document_type_id' => $visuraType?->id,
                'is_required' => true,
            ]
        );

        // --- TASK 2: Controllo Compliance e Antiriciclaggio ---
        $task2 = $process->tasks()->updateOrCreate(
            ['process_id' => $process->id, 'ordine' => 20],
            [
                'name' => 'Valutazione Compliance e AML',
                'has_reminders' => false,
            ]
        );

        // RACI per Task 2 (L'Ufficio Compliance DEVE lavorarlo)
        if ($complianceDept) {
            $task2->raciAssignments()->updateOrCreate(
                ['business_function_id' => $complianceDept->id],
                ['raci_role' => 'R'] // Responsible
            );
        }

        $task2->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task2->id, 'ordine' => 1],
            [
                'name' => 'Compila Modulo AML',
                'action_type' => 'fill_checklist',
                // N.B. In un'architettura completa, qui potresti aggiungere una colonna 'checklist_id' a process_task_items
                // oppure gestirlo tramite logica. Assumiamo che la action fill_checklist usi un campo di configurazione.
                'is_required' => true,
            ]
        );

        $task2->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task2->id, 'ordine' => 2],
            [
                'name' => 'Note di valutazione (Opzionali)',
                'action_type' => 'text_input',
                'is_required' => false,
            ]
        );

        // --- TASK 3: Creazione Utenze (System Task / Automazione) ---
        $task3 = $process->tasks()->updateOrCreate(
            ['process_id' => $process->id, 'ordine' => 30],
            ['name' => 'Generazione Credenziali IT']
        );

        // RACI per Task 3 (Dipartimento IT è responsabile, ma è un'azione automatica)
        if ($itDept) {
            $task3->raciAssignments()->updateOrCreate(
                ['business_function_id' => $itDept->id],
                ['raci_role' => 'R']
            );
        }

        $task3->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task3->id, 'ordine' => 1],
            [
                'name' => 'Provisioning Account Agente',
                'action_type' => 'system_task',
                'handler_job' => 'App\Jobs\ProvisionAgentAccountJob',
                'is_required' => true,
            ]
        );

        $this->seedAgentOffboarding($complianceDept, $itDept);
    }

    /**
     * Processo speculare all'Onboarding: un agente con mandato attivo
     * (stipulated_at valorizzato, dismissed_at ancora vuoto) può essere
     * offboardato; al completamento, valorizziamo dismissed_at.
     */
    private function seedAgentOffboarding(?BusinessFunction $complianceDept, ?BusinessFunction $itDept): void
    {
        $process = Process::firstOrCreate(
            ['code' => 'AGENT_OFFBOARDING', 'version' => 1],
            ['name' => 'Offboarding Agente', 'is_active' => true]
        );

        $process->update([
            'target_model' => 'fornitore',
            'trigger_field' => 'stipulated_at',
            'trigger_state' => 'filled',
            'exclude_field' => 'dismissed_at',
            'exclude_state' => 'filled',
            'completion_write_field' => 'dismissed_at',
            'completion_write_value' => 'now',
            'completion_write_app' => 'unicoloan',
        ]);

        $task1 = $process->tasks()->updateOrCreate(
            ['process_id' => $process->id, 'ordine' => 10],
            ['name' => 'Riconsegna Materiali e Revoca Accessi']
        );

        if ($complianceDept) {
            $task1->raciAssignments()->updateOrCreate(
                ['business_function_id' => $complianceDept->id],
                ['raci_role' => 'A']
            );
        }

        if ($itDept) {
            $task1->raciAssignments()->updateOrCreate(
                ['business_function_id' => $itDept->id],
                ['raci_role' => 'R']
            );
        }

        $task1->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task1->id, 'ordine' => 1],
            [
                'name' => 'Conferma Revoca Accessi e Credenziali',
                'action_type' => 'text_input',
                'is_required' => true,
            ]
        );
    }
}
