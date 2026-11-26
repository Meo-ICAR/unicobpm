<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessTrigger;
use Illuminate\Database\Seeder;

/**
 * Verifiche ispettive periodiche (audit semestrali) sulla rete, comunicate per email dal legale o dal compliance. UnicoPEC legge l'email con l'AI e
 * comunica `email.richiesta` con `richiesta = audit_periodico`: i produttori elencati sono già abbinati alle anagrafiche (`produttori_ids`,
 * `produttori_elenco`) e i nomi ambigui o non trovati sono nei `controlli_testo`. Il processo:
 *  1. avvisa il compliance con l'elenco e chiede di confermarlo (un audit è una comunicazione ufficiale: nomi ambigui o mancanti si sistemano a mano);
 *  2. a elenco confermato accoda in UnicoOAM `audits:open-from-email`, che apre un audit interno per ciascun produttore e gli invia il testo standard
 *     (personalizzato) con questionario e checklist; i produttori mancanti si aggiungono rilanciando il comando dal pannello.
 * Il seeder è idempotente; i ruoli si assegnano da pannello.
 */
class AuditVerificaIspettivaProcessSeeder extends Seeder
{
    public function run(): void
    {
        $compliance = EmployeeType::office('COMPLIANCE', ['name' => 'Ufficio Compliance']);
        $network = EmployeeType::office('SEGR-RETE', ['name' => 'Segreteria di rete']);

        $notice = EmailTemplate::updateOrCreate(['code' => 'AUDIT_RETE_RICHIESTA_RICEVUTA', 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => 'Verifiche ispettive – richiesta ricevuta',
            'type' => 'processo',
            'subject' => 'Verifiche ispettive sulla rete: confermare l\'elenco dei produttori ({external_data.oggetto})',
            'body' => "È arrivata un'email di verifiche ispettive da {external_data.mittente} (riferimento {external_ref}).\n\n"
                ."Sintesi: {external_data.sintesi}\n\n"
                ."Produttori riconosciuti: {external_data.produttori_elenco}\n\n"
                ."Da controllare (nomi ambigui o non trovati, se vuoto è tutto a posto): {external_data.controlli_testo}\n\n"
                ."Confermando l'elenco, UnicoOAM apre un audit interno per ciascun produttore riconosciuto e gli invia il testo standard con questionario e checklist. "
                .'Eventuali produttori mancanti vanno aggiunti rilanciando il comando dal pannello di UnicoOAM.',
            'is_active' => true,
        ]);

        $process = Process::updateOrCreate(['code' => 'PRC-AUDIT-RETE'], [
            'name' => 'Verifiche ispettive periodiche sulla rete',
            'description' => 'Avvio da UnicoPEC (email.richiesta / audit_periodico): conferma dell\'elenco, apertura in UnicoOAM di un audit interno per produttore e invio della comunicazione con questionario e checklist.',
            'is_active' => true,
        ]);

        $confirm = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 10], ['name' => 'Conferma dell\'elenco dei produttori', 'code' => 'audit-conferma']);
        $open = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 20], ['name' => 'Apertura degli audit e invio ai produttori', 'code' => 'audit-apertura']);

        foreach ([$confirm, $open] as $task) {
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $compliance->id], ['raci_role' => 'R']);
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $network->id], ['raci_role' => 'I']);
        }

        $this->item($confirm, 10, 'Avviso al compliance con l\'elenco dei produttori', 'automated_email', false, ['target_business_function' => 'COMPLIANCE', 'email_template_id' => $notice->id]);
        $this->item($confirm, 20, 'Elenco confermato (nomi ambigui o non trovati risolti, o esclusi)', 'text_input', true);
        $this->item($open, 10, 'Apertura degli audit in UnicoOAM e invio della comunicazione ai produttori', 'external_command', false, [
            'app' => 'unicooam',
            'command' => 'audits:open-from-email',
            'options' => ['email' => '{incoming_email_id}', 'produttori' => '{external_data.produttori_ids}'],
            'cc_raci' => ['I'],
        ]);
        $this->item($open, 20, 'Esito controllato: audit aperti e comunicazioni inviate a tutti i produttori (indicare eventuali mancanti)', 'text_input', true);

        ProcessTrigger::updateOrCreate(['event_type' => 'external', 'external_event' => 'email.richiesta', 'name' => 'Email di verifiche ispettive periodiche sulla rete'], [
            'model_class' => 'external',
            'process_id' => $process->id,
            'conditions' => [['field' => 'richiesta', 'operator' => '=', 'value' => 'audit_periodico']],
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
