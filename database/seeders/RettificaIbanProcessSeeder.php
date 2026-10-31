<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\Process;
use App\Models\ProcessTrigger;
use Illuminate\Database\Seeder;
use App\Models\EmployeeType;

/**
 * Rettifica dell'IBAN di un agente o fornitore, comunicata per email. UnicoPEC legge l'email con l'AI e comunica `email.richiesta` con
 * `richiesta = rettifica_iban` (mittente, proforma, IBAN mascherato, `controlli_testo`; mai il corpo: l'IBAN intero si legge con
 * GET /api/incoming-emails/{id} di UnicoPEC). Il cambio di IBAN è il caso tipico di frode via email, quindi il processo NON cambia nulla da solo:
 *  1. avvisa l'amministrazione, segnalando gli eventuali controlli falliti (IBAN estero, mittente sconosciuto...);
 *  2. pretende una richiamata al numero già in anagrafica (mai a quello scritto nell'email) e un documento della banca;
 *  3. solo dopo l'approvazione del responsabile l'IBAN si aggiorna in anagrafica;
 *  4. conferma all'agente.
 * I passi 1 e 2 si sollecitano finché nessuno li prende in carico. Il seeder è idempotente; i ruoli si assegnano da pannello.
 */
class RettificaIbanProcessSeeder extends Seeder
{
    public function run(): void
    {
        $accountant = EmployeeType::office('COGE-CONTABILE', ['name' => 'Contabile']);
        $manager = EmployeeType::office('COGE-CONTABILE-RESP', ['name' => 'Contabile responsabile']);
        $admin = EmployeeType::office('COGE-AMMINISTRATORE', ['name' => 'Amministratore']);
        $compliance = EmployeeType::office('COMPLIANCE', ['name' => 'Ufficio Compliance']);
        $network = EmployeeType::office('SEGR-RETE', ['name' => 'Segreteria di rete']);

        $notice = $this->template('IBAN_RICHIESTA_RICEVUTA', 'Rettifica IBAN – richiesta ricevuta',
            'Richiesta di cambio IBAN da verificare ({external_data.mittente})',
            "{external_data.mittente} ha comunicato per email un nuovo IBAN (riferimento {external_ref}, proforma {external_data.dati.proforma}).\n\n"
            ."IBAN indicato: {external_data.dati.iban}\nOggetto dell'email: {external_data.oggetto}\n"
            ."Sintesi: {external_data.sintesi}\n\n"
            ."Controlli automatici falliti: {external_data.controlli_testo}\n(se vuoto, nessun segnale di rischio: la verifica va comunque fatta)\n\n"
            ."ATTENZIONE: il cambio di IBAN è un tentativo di frode frequente. Non modificare nulla prima della richiamata al numero già in anagrafica "
            ."e del documento della banca. L'IBAN completo si legge solo nel pannello di UnicoPEC.");

        $this->template('IBAN_SOLLECITO', 'Rettifica IBAN – sollecito',
            'Sollecito: richiesta di cambio IBAN {riferimento} ferma da {ore} ore',
            "La richiesta di cambio IBAN {riferimento} è ferma da {ore} ore (sollecito di livello {livello}).\n\nÈ ancora aperta la verifica: richiamare l'agente al numero in anagrafica e raccogliere il documento della banca.");

        $confirm = $this->template('IBAN_CONFERMA_AGENTE', 'Rettifica IBAN – conferma all\'agente',
            'Il tuo nuovo IBAN è stato registrato',
            "Gentile collaboratore,\n\nabbiamo verificato e registrato il nuovo IBAN comunicato per il proforma {external_data.dati.proforma}.\n"
            ."I prossimi pagamenti saranno disposti sul nuovo conto. Se non hai richiesto tu questa modifica, rispondi subito a questa email.");

        $process = Process::updateOrCreate(['code' => 'PRC-IBAN'], [
            'name' => 'Rettifica IBAN di un agente',
            'description' => 'Avvio da UnicoPEC (email.richiesta / rettifica_iban): verifica con richiamata e documento della banca, approvazione, aggiornamento anagrafica, conferma.',
            'is_active' => true,
        ]);

        $verify = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 10], [
            'name' => 'Verifica della richiesta',
            'code' => 'iban-verifica',
            'escalation_rules' => [
                ['level' => 1, 'delay_hours' => 24, 'action' => 'notify_assignee', 'until_claimed' => true, 'email_template_code' => 'IBAN_SOLLECITO'],
                ['level' => 2, 'delay_hours' => 72, 'action' => 'notify_manager', 'until_claimed' => true, 'email_template_code' => 'IBAN_SOLLECITO'],
            ],
        ]);
        $approve = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 20], ['name' => 'Approvazione del responsabile', 'code' => 'iban-approvazione']);
        $update = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 30], ['name' => 'Aggiornamento anagrafica e conferma', 'code' => 'iban-aggiornamento']);

        foreach ([$verify, $update] as $task) {
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $accountant->id], ['raci_role' => 'R']);
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $manager->id], ['raci_role' => 'A']);
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $network->id], ['raci_role' => 'I']);
        }
        $verify->raciAssignments()->updateOrCreate(['employee_type_id' => $compliance->id], ['raci_role' => 'C']);
        $approve->raciAssignments()->updateOrCreate(['employee_type_id' => $manager->id], ['raci_role' => 'R']);
        $approve->raciAssignments()->updateOrCreate(['employee_type_id' => $admin->id], ['raci_role' => 'A']);
        $approve->raciAssignments()->updateOrCreate(['employee_type_id' => $compliance->id], ['raci_role' => 'C']);

        $this->item($verify, 10, 'Avviso all\'amministrazione', 'automated_email', false, ['target_business_function' => 'COGE-CONTABILE', 'email_template_id' => $notice->id]);
        $this->item($verify, 20, 'Richiamata all\'agente al numero già in anagrafica (data, ora, esito; non il numero scritto nell\'email)', 'text_input', true);
        $this->item($verify, 30, 'Documento della banca che attesta la titolarità del nuovo IBAN (estremi del documento)', 'text_input', true);
        $this->item($verify, 40, 'Il nuovo IBAN è IT, valido e intestato all\'agente? (sì/no e note)', 'text_input', true);
        $this->item($approve, 10, 'Decisione: approvato o rifiutato, con motivazione', 'text_input', true);
        $this->item($update, 10, 'IBAN aggiornato in anagrafica (indicare data e chi ha operato)', 'text_input', true);
        $this->item($update, 20, 'Conferma all\'agente', 'automated_email', false, ['to_email' => '{external_data.mittente}', 'email_template_id' => $confirm->id]);

        ProcessTrigger::updateOrCreate(['event_type' => 'external', 'external_event' => 'email.richiesta', 'name' => 'Email con richiesta di rettifica IBAN'], [
            'model_class' => 'external',
            'process_id' => $process->id,
            'conditions' => [['field' => 'richiesta', 'operator' => '=', 'value' => 'rettifica_iban']],
            'is_active' => true,
        ]);
    }

    private function template(string $code, string $name, string $subject, string $body): EmailTemplate
    {
        return EmailTemplate::updateOrCreate(['code' => $code, 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => $name, 'type' => 'processo', 'subject' => $subject, 'body' => $body, 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed>|null $config */
    private function item($task, int $order, string $name, string $type, bool $required = false, ?array $config = null): void
    {
        $task->processTaskItems()->updateOrCreate(['process_task_id' => $task->id, 'ordine' => $order], [
            'name' => $name, 'action_type' => $type, 'is_required' => $required, 'config' => $config,
        ]);
    }
}
