<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\Process;
use App\Models\ProcessTrigger;
use Illuminate\Database\Seeder;
use App\Models\EmployeeType;

/**
 * Validazione dell'importo di una fattura ricevuta rispetto al proforma. UnicoPEC comunica `email.richiesta` con `richiesta = fattura_ricevuta`
 * (mittente, proforma citato se c'è, numero di allegati; mai il corpo). Il processo:
 *  1. chiede a UnicoCoge di riconciliare le fatture di acquisto con i proforma (`proformas:match-purchase-invoices`, per uguaglianza di importo);
 *  2. UnicoPEC ha già letto l'allegato con l'AI e confrontato l'importo con il totale del proforma dell'oggetto: l'esito (`confronto.esito`:
 *     coincide | differenza | non_determinabile, con `confronto.dettaglio`) arriva nell'evento e il processo lo comunica al contabile, che lo verifica;
 *  3. se gli importi non coincidono (una differenza di 2,00 è di norma la marca da bollo) avvisa l'agente e il responsabile e il pagamento resta fermo;
 *     se coincidono la fattura passa al pagamento.
 * Una fattura che non coincide non va mai pagata «a occhio»: la differenza si spiega (trattenuta Enasarco, nota di credito, proforma da correggere).
 * Il seeder è idempotente; i ruoli si assegnano da pannello.
 */
class ValidazioneFatturaProformaProcessSeeder extends Seeder
{
    public function run(): void
    {
        $accountant = EmployeeType::office('COGE-CONTABILE', ['name' => 'Contabile']);
        $manager = EmployeeType::office('COGE-CONTABILE-RESP', ['name' => 'Contabile responsabile']);
        $admin = EmployeeType::office('COGE-AMMINISTRATORE', ['name' => 'Amministratore']);

        $outcomeNotice = EmailTemplate::updateOrCreate(['code' => 'FATTURA_ESITO_CONFRONTO', 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => 'Fattura – esito del confronto automatico con il proforma',
            'type' => 'processo',
            'subject' => 'Fattura di {external_data.mittente}: confronto con il proforma = {external_data.confronto.esito}',
            'body' => "È arrivata una fattura da {external_data.mittente} (oggetto «{external_data.oggetto}»).\n\n"
                ."UnicoPEC ha letto l'allegato con l'AI e l'ha confrontato con il totale del proforma:\n{external_data.confronto.dettaglio}\n\n"
                ."Esito: {external_data.confronto.esito}. Verificare nel passo «Confronto con il proforma» (una differenza di 2,00 è di norma la marca da bollo).",
            'is_active' => true,
        ]);
        $mismatch = EmailTemplate::updateOrCreate(['code' => 'FATTURA_IMPORTO_DIVERSO', 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => 'Fattura – importo diverso dal proforma',
            'type' => 'processo',
            'subject' => 'Fattura {external_ref}: l\'importo non coincide con il proforma',
            'body' => "La fattura ricevuta da {external_data.mittente} (oggetto «{external_data.oggetto}», proforma {external_data.dati.proforma}) ha un importo diverso dal proforma.\n\n"
                ."Esito del confronto: vedi il passo «Confronto con il proforma» della pratica {external_ref}.\n\nIl pagamento resta fermo finché la differenza non è chiarita: trattenuta Enasarco, nota di credito o proforma da correggere.",
            'is_active' => true,
        ]);
        $agent = EmailTemplate::updateOrCreate(['code' => 'FATTURA_RICHIESTA_RETTIFICA', 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => 'Fattura – richiesta di rettifica all\'agente',
            'type' => 'processo',
            'subject' => 'La fattura inviata non corrisponde al proforma {external_data.dati.proforma}',
            'body' => "Gentile collaboratore,\n\nl'importo della fattura che ci hai inviato non corrisponde al proforma {external_data.dati.proforma}.\n"
                .'Ti chiediamo di verificarla e di inviarci una fattura corretta, oppure una nota di credito, indicando nella causale il riferimento del proforma.',
            'is_active' => true,
        ]);

        $process = Process::updateOrCreate(['code' => 'PRC-FATT-PROFORMA'], [
            'name' => 'Validazione fattura con il proforma',
            'description' => 'Avvio da UnicoPEC (email.richiesta / fattura_ricevuta): riconciliazione su UnicoCoge, confronto dell\'importo con il proforma, richiesta di rettifica o via libera al pagamento.',
            'is_active' => true,
        ]);

        $match = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 10], ['name' => 'Riconciliazione automatica', 'code' => 'fattura-riconciliazione']);
        $compare = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 20], ['name' => 'Confronto con il proforma', 'code' => 'fattura-confronto']);
        $outcome = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 30], ['name' => 'Esito: pagamento o rettifica', 'code' => 'fattura-esito']);

        foreach ([$match, $compare, $outcome] as $task) {
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $accountant->id], ['raci_role' => 'R']);
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $manager->id], ['raci_role' => 'A']);
        }
        $outcome->raciAssignments()->updateOrCreate(['employee_type_id' => $admin->id], ['raci_role' => 'I']);

        $this->item($match, 10, 'Riconcilia fatture di acquisto e proforma su UnicoCoge', 'external_command', false, ['app' => 'unicocoge', 'command' => 'proformas:match-purchase-invoices', 'cc_raci' => ['A']]);
        $this->item($compare, 5, 'Esito del confronto automatico (UnicoPEC) al contabile', 'automated_email', false, ['target_business_function' => 'COGE-CONTABILE', 'email_template_id' => $outcomeNotice->id]);
        $this->item($compare, 10, 'Numero del proforma a cui si riferisce la fattura', 'text_input', true);
        $this->item($compare, 20, 'Importo della fattura verificato sull\'allegato (euro)', 'text_input', true);
        $this->item($compare, 30, 'Totale del proforma verificato su UnicoCoge (euro)', 'text_input', true);
        $this->item($compare, 40, 'Conferma l\'esito del confronto automatico? (coincide / differenza, con il motivo: es. marca da bollo, trattenuta, nota di credito)', 'text_input', true);
        $this->item($outcome, 10, 'Se non coincidono: avviso al responsabile', 'automated_email', false, ['target_business_function' => 'COGE-CONTABILE-RESP', 'email_template_id' => $mismatch->id]);
        $this->item($outcome, 20, 'Se non coincidono: richiesta di rettifica all\'agente', 'automated_email', false, ['to_email' => '{external_data.mittente}', 'email_template_id' => $agent->id]);
        $this->item($outcome, 30, 'Esito finale (via libera al pagamento / in attesa di rettifica)', 'text_input', true);

        ProcessTrigger::updateOrCreate(['event_type' => 'external', 'external_event' => 'email.richiesta', 'name' => 'Email con fattura da validare'], [
            'model_class' => 'external',
            'process_id' => $process->id,
            'conditions' => [['field' => 'richiesta', 'operator' => '=', 'value' => 'fattura_ricevuta']],
            'is_active' => true,
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
