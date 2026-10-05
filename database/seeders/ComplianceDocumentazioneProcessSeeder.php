<?php

namespace Database\Seeders;

use App\Enums\Severity;
use App\Models\BusinessFunction;
use App\Models\EmailTemplate;
use App\Models\Process;
use Illuminate\Database\Seeder;

/**
 * Processo ricorrente "Compliance": ogni giorno (bpm:run-scheduler) apre una pratica senza soggetto
 * il cui task "Documentazione" interroga UnicoOAM (documents:check-expired): il valore restituito è
 * l'elenco dei documenti scaduti dello Scadenziario, la severity dipende dal ritardo della scadenza
 * più vecchia (regular > 7 giorni, warning > 15, alert > 30).
 *
 * RACI: R = Segreteria, A = Ufficio Compliance, I = Amministratore (la stessa funzione dello Scadenziario COGE);
 * i membri vanno assegnati da pannello in "Funzioni aziendali". Il testo dei template email si rifinisce da pannello.
 */
class ComplianceDocumentazioneProcessSeeder extends Seeder
{
    private const TEMPLATE_CODE = 'COMPL_DOC_SCADUTI';

    public function run(): void
    {
        $functions = [
            'R' => BusinessFunction::updateOrCreate(['code' => 'SEGRETERIA'], ['name' => 'Segreteria']),
            'A' => BusinessFunction::updateOrCreate(['code' => 'COMPLIANCE'], ['name' => 'Ufficio Compliance']),
            'I' => BusinessFunction::updateOrCreate(['code' => 'COGE-AMMINISTRATORE'], ['name' => 'Amministratore']),
        ];

        $process = Process::updateOrCreate(['code' => 'PRC-COMPLIANCE'], [
            'name' => 'Compliance',
            'description' => 'Controllo giornaliero della documentazione scaduta rilevata dallo Scadenziario di UnicoOAM: UnicoBPM avvisa i ruoli RACI in base alla severity.',
            'is_active' => true,
            'is_periodic' => true,
            'recurrence_frequency' => 'daily',
        ]);

        $task = $process->tasks()->updateOrCreate(
            ['process_id' => $process->id, 'ordine' => 10],
            ['name' => 'Documentazione', 'code' => 'compliance-documentazione']
        );

        foreach ($functions as $role => $function) {
            $task->raciAssignments()->updateOrCreate(
                ['business_function_id' => $function->id],
                ['raci_role' => $role]
            );
        }

        $task->raciAssignments()->whereNotIn('business_function_id', collect($functions)->pluck('id'))->delete();

        $task->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task->id, 'ordine' => 10],
            [
                'name' => 'Documenti scaduti',
                'action_type' => 'external_check',
                'is_required' => true,
                'config' => [
                    'app' => 'unicooam',
                    'command' => 'documents:check-expired',
                    'email_template_code' => self::TEMPLATE_CODE,
                ],
            ]
        );

        $this->seedEmailTemplates();
    }

    /**
     * Un template per ogni severity che prevede un'email (ok no), con tono e urgenza crescenti.
     * Il seeder riallinea sempre il testo: eventuali modifiche da pannello vanno rifatte dopo averlo rieseguito.
     */
    private function seedEmailTemplates(): void
    {
        $tones = [
            Severity::Regular->value => ['prefix' => 'Segnalazione', 'text' => 'Segnalazione ordinaria: ci sono documenti scaduti da oltre 7 giorni.'],
            Severity::Warning->value => ['prefix' => 'Attenzione', 'text' => 'Attenzione: ci sono documenti scaduti da oltre 15 giorni, serve un intervento a breve.'],
            Severity::Alert->value => ['prefix' => 'ALLERTA', 'text' => 'ALLERTA: ci sono documenti scaduti da oltre 30 giorni, serve un intervento immediato.'],
        ];

        foreach ([Severity::Regular, Severity::Warning, Severity::Alert] as $severity) {
            $tone = $tones[$severity->value];

            EmailTemplate::updateOrCreate(
                ['code' => self::TEMPLATE_CODE, 'severity' => $severity->value],
                [
                    'name' => "Documenti scaduti — {$severity->label()}",
                    'subject' => "{$tone['prefix']} Compliance: {details}",
                    'body' => "{$tone['text']}\n\n{details}\n\nElenco dei documenti scaduti (Scadenziario):\n{value}\n\nQuesta email è stata generata automaticamente da UnicoBPM (processo Compliance).",
                    'placeholders' => ['{value}', '{severity}', '{details}'],
                    'is_active' => true,
                ]
            );
        }
    }
}
