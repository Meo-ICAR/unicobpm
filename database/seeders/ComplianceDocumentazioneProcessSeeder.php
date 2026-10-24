<?php

namespace Database\Seeders;

use App\Enums\Severity;
use App\Models\EmailTemplate;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessTask;
use Illuminate\Database\Seeder;

/**
 * Processo ricorrente "Compliance": ogni giorno (bpm:run-scheduler) apre una pratica senza soggetto con due passi di controllo.
 *  - «Documentazione» interroga UnicoOAM: `documents:check-expired` (documenti scaduti dello Scadenziario, sempre alert) e
 *    `documents:check-expiring` (in scadenza: warning entro 7 giorni, regular entro 15).
 *  - «Fatture di acquisto» interroga UnicoCoge: `purchase-invoices:check-stale`, l'età dell'ultima fattura di acquisto caricata
 *    (regular da 25 giorni, warning da 31, alert da 45).
 *
 *  - «Provvigioni del mese» (UnicoCoge) lavora a date fisse del mese (`run_on_day`), con i destinatari presi dalla RACI del passo (R contabile,
 *    A contabile responsabile, C area commerciale, I amministratore): il 3 elenco delle provvigioni maturate (CSV allegato) ad A, C e I;
 *    il 5 promemoria a R per rettificare o escludere; il 10 invio dei proforma ai produttori (`proformas:send-pending`) con in copia A e I.
 *
 * RACI: documentazione R = Segreteria, A = Ufficio Compliance, I = Amministratore; fatture R = Contabile, A = Contabile responsabile,
 * I = Amministratore. I membri si assegnano nella scheda del dipendente (campo Ruoli). Il testo dei template email si rifinisce
 * da pannello, ma il seeder lo riallinea a ogni esecuzione.
 */
class ComplianceDocumentazioneProcessSeeder extends Seeder
{
    private const TEMPLATE_EXPIRED = 'COMPL_DOC_SCADUTI';

    private const TEMPLATE_EXPIRING = 'COMPL_DOC_IN_SCADENZA';

    private const TEMPLATE_INVOICES = 'COMPL_FATTURE_ACQUISTO';

    private const TEMPLATE_MATURATE = 'COMPL_PROVV_MATURATE';

    private const TEMPLATE_RETTIFICA = 'COMPL_PROVV_RETTIFICA';

    public function run(): void
    {
        $process = Process::updateOrCreate(['code' => 'PRC-COMPLIANCE'], [
            'name' => 'Compliance',
            'description' => "Controllo giornaliero di compliance. Ogni mattina UnicoBPM apre una pratica e interroga le altre app:\n"
                ."• Documenti scaduti (UnicoOAM, Scadenziario): un documento scaduto è sempre un alert.\n"
                ."• Documenti in scadenza (UnicoOAM): avviso entro 15 giorni (regular) e entro 7 giorni (warning); sono esclusi i documenti già rinnovati o sostituiti e quelli di produttori dimessi e dipendenti cessati.\n"
                ."• Fatture di acquisto (UnicoCoge): se l'ultima fattura caricata ha più di 25 giorni avvisa (regular), da 31 giorni warning, da 45 giorni alert.\n"
                ."• Provvigioni del mese (UnicoCoge): il 3 invio dell'elenco delle provvigioni maturate (CSV allegato) ai ruoli A, C e I della RACI (contabile responsabile, area commerciale, amministratore); il 5 promemoria a R (contabile) per rettificare o escludere; il 10 invio automatico dei proforma ai produttori, in copia A e I della RACI.\n"
                .'Le email partono ai ruoli RACI del passo secondo la gravità e il testo si sceglie per grado di severity.',
            'is_active' => true,
            'is_periodic' => true,
            'recurrence_frequency' => 'daily',
        ]);

        $documents = $this->task($process, 10, 'Documentazione', 'compliance-documentazione', [
            'R' => EmployeeType::office('SEGRETERIA', ['name' => 'Segreteria']),
            'A' => EmployeeType::office('COMPLIANCE', ['name' => 'Ufficio Compliance']),
            'I' => EmployeeType::office('COGE-AMMINISTRATORE', ['name' => 'Amministratore']),
        ]);
        $this->check($documents, 10, 'Documenti scaduti', 'unicooam', 'documents:check-expired', self::TEMPLATE_EXPIRED);
        $this->check($documents, 20, 'Documenti in scadenza', 'unicooam', 'documents:check-expiring', self::TEMPLATE_EXPIRING);

        $invoices = $this->task($process, 20, 'Fatture di acquisto', 'compliance-fatture-acquisto', [
            'R' => EmployeeType::office('COGE-CONTABILE', ['name' => 'Contabile']),
            'A' => EmployeeType::office('COGE-CONTABILE-RESP', ['name' => 'Contabile responsabile']),
            'I' => EmployeeType::office('COGE-AMMINISTRATORE', ['name' => 'Amministratore']),
        ]);
        $this->check($invoices, 10, 'Ultima fattura di acquisto', 'unicocoge', 'purchase-invoices:check-stale', self::TEMPLATE_INVOICES);

        $month = $this->task($process, 30, 'Provvigioni del mese', 'compliance-provvigioni-mese', [
            'R' => EmployeeType::office('COGE-CONTABILE', ['name' => 'Contabile']),
            'A' => EmployeeType::office('COGE-CONTABILE-RESP', ['name' => 'Contabile responsabile']),
            'C' => EmployeeType::office('COMMERCIALE', ['name' => 'Area Commerciale']),
            'I' => EmployeeType::office('COGE-AMMINISTRATORE', ['name' => 'Amministratore']),
        ]);
        $this->check($month, 10, 'Il 3: elenco provvigioni maturate', 'unicocoge', 'provvigioni:check-maturate', self::TEMPLATE_MATURATE, [
            'run_on_day' => 3,
            'to_raci' => ['A', 'C', 'I'],
        ]);
        $this->check($month, 20, 'Il 5: rettifica o esclusione provvigioni', 'unicocoge', 'provvigioni:check-maturate', self::TEMPLATE_RETTIFICA, [
            'run_on_day' => 5,
            'to_raci' => ['R'],
        ]);
        $month->processTaskItems()->updateOrCreate(
            ['process_task_id' => $month->id, 'ordine' => 30],
            [
                'name' => 'Il 10: invio proforma ai produttori',
                'action_type' => 'external_command',
                'is_required' => true,
                'config' => [
                    'app' => 'unicocoge',
                    'command' => 'proformas:send-pending',
                    'run_on_day' => 10,
                    'cc_raci' => ['A', 'I'],
                ],
            ]
        );

        $this->seedEmailTemplates(self::TEMPLATE_MATURATE, 'Provvigioni maturate', 'Riepilogo (l\'elenco completo è nel CSV allegato)', [
            Severity::Regular->value => ['Provvigioni maturate', 'Sono maturate provvigioni da portare in proforma: in allegato l\'elenco aggiornato a oggi.'],
        ]);
        $this->seedEmailTemplates(self::TEMPLATE_RETTIFICA, 'Rettifica provvigioni', 'Provvigioni ancora da lavorare (elenco completo nel CSV allegato)', [
            Severity::Regular->value => ['Da rettificare o escludere', 'Entro oggi vanno rettificate o escluse le provvigioni che non devono entrare nei proforma: il 10 i proforma partono in automatico ai produttori.'],
        ]);

        $this->seedEmailTemplates(self::TEMPLATE_EXPIRED, 'Documenti scaduti', 'Elenco dei documenti scaduti (Scadenziario)', [
            Severity::Regular->value => ['Segnalazione', 'Segnalazione ordinaria: ci sono documenti scaduti.'],
            Severity::Warning->value => ['Attenzione', 'Attenzione: ci sono documenti scaduti, serve un intervento a breve.'],
            Severity::Alert->value => ['ALLERTA', 'ALLERTA: ci sono documenti scaduti, serve un intervento immediato.'],
        ]);
        $this->seedEmailTemplates(self::TEMPLATE_EXPIRING, 'Documenti in scadenza', 'Elenco dei documenti in scadenza', [
            Severity::Regular->value => ['Promemoria', 'Promemoria: ci sono documenti che scadono entro 15 giorni.'],
            Severity::Warning->value => ['Attenzione', 'Attenzione: ci sono documenti che scadono entro 7 giorni.'],
            Severity::Alert->value => ['ALLERTA', 'ALLERTA: ci sono documenti già scaduti.'],
        ]);
        $this->seedEmailTemplates(self::TEMPLATE_INVOICES, 'Fatture di acquisto', 'Età dell\'ultima fattura di acquisto caricata (giorni)', [
            Severity::Regular->value => ['Promemoria', "Promemoria: l'ultima fattura di acquisto risale a più di 25 giorni fa, verificare il caricamento."],
            Severity::Warning->value => ['Attenzione', "Attenzione: l'ultima fattura di acquisto risale a più di 31 giorni fa, il caricamento sembra fermo."],
            Severity::Alert->value => ['ALLERTA', "ALLERTA: l'ultima fattura di acquisto risale a più di 45 giorni fa, il caricamento è fermo."],
        ]);
    }

    /**
     * @param  array<string, EmployeeType>  $functions  Ruolo RACI => ruolo dell'ufficio.
     */
    private function task(Process $process, int $order, string $name, string $code, array $functions): ProcessTask
    {
        $task = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => $order], ['name' => $name, 'code' => $code]);

        foreach ($functions as $role => $function) {
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $function->id], ['raci_role' => $role]);
        }

        $task->raciAssignments()->whereNotIn('employee_type_id', collect($functions)->pluck('id'))->delete();

        return $task;
    }

    /**
     * @param  array<string, mixed>  $extra  Altre voci di config (es. `run_on_day`, `to_raci`).
     */
    private function check(ProcessTask $task, int $order, string $name, string $app, string $command, string $templateCode, array $extra = []): void
    {
        $task->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task->id, 'ordine' => $order],
            [
                'name' => $name,
                'action_type' => 'external_check',
                'is_required' => true,
                'config' => ['app' => $app, 'command' => $command, 'email_template_code' => $templateCode] + $extra,
            ]
        );
    }

    /**
     * Un template per ogni severity indicata in `$tones`, con tono e urgenza crescenti.
     *
     * @param  array<string, array{0: string, 1: string}>  $tones  Severity => [prefisso dell'oggetto, testo introduttivo].
     */
    private function seedEmailTemplates(string $code, string $title, string $valueLabel, array $tones): void
    {
        foreach (array_keys($tones) as $severityValue) {
            $severity = Severity::from($severityValue);
            [$prefix, $text] = $tones[$severityValue];

            EmailTemplate::updateOrCreate(
                ['code' => $code, 'severity' => $severity->value],
                [
                    'name' => "{$title} — {$severity->label()}",
                    'subject' => "{$prefix} Compliance: {details}",
                    'body' => "{$text}\n\n{details}\n\n{$valueLabel}:\n{value}\n\nQuesta email è stata generata automaticamente da UnicoBPM (processo Compliance).",
                    'placeholders' => ['{value}', '{severity}', '{details}'],
                    'is_active' => true,
                ]
            );
        }
    }
}
