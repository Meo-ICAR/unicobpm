<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\EmailTemplate;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessTrigger;
use Illuminate\Database\Seeder;

/**
 * Gestione delle segnalazioni di UnicoWhistle (D.Lgs. 24/2023). Whistle comunica `segnalazione.ricevuta` (solo riferimento e scadenze,
 * mai il contenuto) e qui nasce un processo che:
 *  1. avvisa subito per email i membri della funzione «Whistleblowing» col ruolo «responsabile whistleblowing»;
 *  2. sollecita finché nessuno prende in carico la pratica (24 h al responsabile, 72 h e 120 h alla funzione supplente);
 *  3. porta la data tassativa al termine di riscontro di 3 mesi indicato da Whistle.
 *
 * Il ruolo (EmployeeType) e le funzioni vanno popolati da pannello: si assegna il ruolo ai membri della funzione «Whistleblowing»
 * (Funzioni aziendali) e, volendo, i supplenti alla funzione «Whistleblowing – supplenti». Il seeder è idempotente.
 */
class WhistleblowingProcessSeeder extends Seeder
{
    public const ROLE = 'responsabile whistleblowing';

    public function run(): void
    {
        EmployeeType::firstOrCreate(['name' => self::ROLE], ['companytype' => 'COMPLIANCE', 'is_external' => false]);

        $responsible = BusinessFunction::updateOrCreate(['code' => 'WHISTLEBLOWING'], [
            'name' => 'Whistleblowing',
            'description' => 'Gestione delle segnalazioni: i membri col ruolo «'.self::ROLE.'» ricevono l\'avviso di ogni nuova segnalazione.',
        ]);
        $deputy = BusinessFunction::updateOrCreate(['code' => 'WHISTLEBLOWING-SUPPLENTI'], [
            'name' => 'Whistleblowing – supplenti',
            'description' => 'Ricevono i solleciti se la segnalazione non viene presa in carico.',
        ]);

        $template = EmailTemplate::updateOrCreate(['code' => 'WHISTLE_NUOVA_SEGNALAZIONE', 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => 'Whistleblowing – nuova segnalazione',
            'type' => 'processo',
            'subject' => 'Nuova segnalazione whistleblowing ricevuta ({external_ref})',
            'body' => "È stata ricevuta una nuova segnalazione (riferimento {external_ref}).\n\n"
                ."Per riservatezza l'email non contiene né il contenuto né i dati di chi segnala: i dettagli si vedono solo accedendo al pannello di gestione ({external_data.url}).\n\n"
                ."Scadenze di legge (D.Lgs. 24/2023):\n- avviso di ricevimento al segnalante entro il {external_data.acknowledgement_due_at}\n- riscontro entro il {external_data.feedback_due_at}\n\n"
                .'Il processo resta aperto finché la segnalazione non viene presa in carico da UnicoBPM («Prendi in carico»): in mancanza partono i solleciti.',
            'is_active' => true,
        ]);

        $process = Process::updateOrCreate(['code' => 'PRC-WHISTLE'], [
            'name' => 'Gestione segnalazione whistleblowing',
            'description' => 'Avvio da UnicoWhistle: avviso al responsabile, presa in carico, avviso di ricevimento (7 giorni) e riscontro (3 mesi).',
            'is_active' => true,
        ]);

        $take = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 10], [
            'name' => 'Presa in carico e avviso di ricevimento',
            'code' => 'whistle-presa-in-carico',
            'escalation_rules' => [
                ['level' => 1, 'delay_hours' => 24, 'action' => 'notify_assignee', 'until_claimed' => true, 'email_template_code' => 'WHISTLE_SOLLECITO'],
                ['level' => 2, 'delay_hours' => 72, 'action' => 'notify_manager', 'until_claimed' => true, 'email_template_code' => 'WHISTLE_SOLLECITO'],
                ['level' => 3, 'delay_hours' => 120, 'action' => 'notify_manager', 'until_claimed' => true, 'email_template_code' => 'WHISTLE_SOLLECITO'],
            ],
        ]);
        $feedback = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 20], [
            'name' => 'Riscontro al segnalante',
            'code' => 'whistle-riscontro',
        ]);

        foreach ([$take, $feedback] as $task) {
            $task->raciAssignments()->updateOrCreate(['business_function_id' => $responsible->id], ['raci_role' => 'R']);
            $task->raciAssignments()->updateOrCreate(['business_function_id' => $deputy->id], ['raci_role' => 'A']);
        }

        $take->processTaskItems()->updateOrCreate(['process_task_id' => $take->id, 'ordine' => 10], [
            'name' => 'Avviso al responsabile whistleblowing',
            'action_type' => 'automated_email',
            'is_required' => false,
            'config' => ['target_business_function' => 'WHISTLEBLOWING', 'to_role' => self::ROLE, 'email_template_id' => $template->id],
        ]);
        $take->processTaskItems()->updateOrCreate(['process_task_id' => $take->id, 'ordine' => 20], [
            'name' => 'Avviso di ricevimento inviato al segnalante (indicare la data)',
            'action_type' => 'text_input',
            'is_required' => true,
            'config' => null,
        ]);
        $feedback->processTaskItems()->updateOrCreate(['process_task_id' => $feedback->id, 'ordine' => 10], [
            'name' => 'Esito del riscontro dato al segnalante',
            'action_type' => 'text_input',
            'is_required' => true,
            'config' => null,
        ]);

        EmailTemplate::updateOrCreate(['code' => 'WHISTLE_SOLLECITO', 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => 'Whistleblowing – sollecito di presa in carico',
            'type' => 'processo',
            'subject' => 'Sollecito: segnalazione {riferimento} non ancora presa in carico',
            'body' => "La segnalazione {riferimento} è ferma da {ore} ore (sollecito di livello {livello}).\n\nL'avviso di ricevimento al segnalante va dato entro 7 giorni dalla ricezione. Accedere a UnicoBPM e premere «Prendi in carico».",
            'is_active' => true,
        ]);

        ProcessTrigger::updateOrCreate(['event_type' => 'external', 'external_event' => 'segnalazione.ricevuta'], [
            'name' => 'Segnalazione whistleblowing ricevuta',
            'model_class' => 'external',
            'process_id' => $process->id,
            'is_active' => true,
        ]);
    }
}
