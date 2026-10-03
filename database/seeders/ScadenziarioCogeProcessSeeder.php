<?php

namespace Database\Seeders;

use App\Enums\Severity;
use App\Models\BusinessFunction;
use App\Models\EmailTemplate;
use App\Models\EmployeeType;
use App\Models\Process;
use Illuminate\Database\Seeder;

/**
 * Processo ricorrente "Scadenziario COGE": ogni giorno (bpm:run-scheduler) apre una pratica senza
 * soggetto il cui unico task "Scadenze" interroga i check di Proforma (action_type 'external_check').
 * Per ogni check, UnicoBPM sceglie il template email (code + severity) e scrive ai ruoli RACI del
 * task in base al grado (regular: R; warning: R+A; alert: R+A+C).
 *
 * La RACI punta a funzioni aziendali: i membri (Employee) con il relativo tipo vanno assegnati da
 * pannello in "Funzioni aziendali"; i template email vivono su unicooam e si rifiniscono da pannello.
 */
class ScadenziarioCogeProcessSeeder extends Seeder
{
    /**
     * Check di Proforma: comando esposto, nome dell'azione, codice template e descrizione per il testo email.
     *
     * @var array<int, array{command: string, name: string, template: string, subject: string, intro: string}>
     */
    private const CHECKS = [
        [
            'command' => 'clienti:check-missing-piva',
            'name' => 'Istituti attivi senza partita IVA',
            'template' => 'COGE_CLIENTI_PIVA',
            'subject' => 'istituti attivi senza partita IVA',
            'intro' => 'Ci sono istituti attivi (non fittizi) senza partita IVA su Proforma.',
        ],
        [
            'command' => 'fornitori:check-missing-email',
            'name' => 'Fornitori senza email',
            'template' => 'COGE_FORNITORI_EMAIL',
            'subject' => 'fornitori senza email',
            'intro' => 'Ci sono fornitori su Proforma senza indirizzo email: non è possibile inviare loro i proforma.',
        ],
        [
            'command' => 'proformas:check-unpaid',
            'name' => 'Proforma inviati e non pagati',
            'template' => 'COGE_PROFORMA_NON_PAGATI',
            'subject' => 'proforma inviati e non pagati',
            'intro' => 'Ci sono proforma inviati da oltre 30 giorni e non ancora pagati.',
        ],
        [
            'command' => 'sales-invoices:check-stale',
            'name' => 'Fatture di vendita non aggiornate',
            'template' => 'COGE_FATTURE_VENDITA_FERME',
            'subject' => 'fatture di vendita non aggiornate',
            'intro' => "Il caricamento delle fatture di vendita su Proforma sembra fermo (il valore indica i giorni dall'ultima fattura).",
        ],
    ];

    public function run(): void
    {
        $functions = $this->seedRaciFunctions();

        $process = Process::updateOrCreate(['code' => 'PRC-COGE'], [
            'name' => 'Scadenziario COGE',
            'description' => 'Controllo giornaliero delle scadenze e delle anomalie contabili rilevate da Proforma: UnicoBPM avvisa i ruoli RACI in base alla severity di ogni check.',
            'is_active' => true,
            'is_periodic' => true,
            'recurrence_frequency' => 'daily',
        ]);

        $task = $process->tasks()->updateOrCreate(
            ['process_id' => $process->id, 'ordine' => 10],
            ['name' => 'Scadenze', 'code' => 'coge-scadenze']
        );

        foreach (['R', 'A', 'C', 'I'] as $role) {
            $task->raciAssignments()->updateOrCreate(
                ['business_function_id' => $functions[$role]->id],
                ['raci_role' => $role]
            );
        }

        // Se un ruolo cambia funzione, le assegnazioni non più previste vengono rimosse.
        $task->raciAssignments()->whereNotIn('business_function_id', collect($functions)->pluck('id'))->delete();

        foreach (self::CHECKS as $index => $check) {
            $task->processTaskItems()->updateOrCreate(
                ['process_task_id' => $task->id, 'ordine' => ($index + 1) * 10],
                [
                    'name' => $check['name'],
                    'action_type' => 'external_check',
                    'is_required' => true,
                    'config' => [
                        'app' => 'proforma',
                        'command' => $check['command'],
                        'email_template_code' => $check['template'],
                    ],
                ]
            );

            $this->seedEmailTemplates($check);
        }
    }

    /**
     * @return array<string, BusinessFunction> Funzione aziendale per ruolo RACI.
     */
    private function seedRaciFunctions(): array
    {
        $definitions = [
            'R' => ['code' => 'COGE-CONTABILE', 'name' => 'Contabile', 'type' => 'contabile', 'is_external' => false],
            'A' => ['code' => 'COGE-CONTABILE-RESP', 'name' => 'Contabile Responsabile', 'type' => 'contabile responsabile', 'is_external' => false],
            'C' => ['code' => 'COGE-CONSULENTE', 'name' => 'Consulente COGE', 'type' => 'consulente coge', 'is_external' => true],
            'I' => ['code' => 'COGE-AMMINISTRATORE', 'name' => 'Amministratore', 'type' => 'amministratore', 'is_external' => false],
        ];

        $functions = [];

        foreach ($definitions as $role => $definition) {
            EmployeeType::firstOrCreate(
                ['name' => $definition['type']],
                ['companytype' => 'FINANCE', 'is_external' => $definition['is_external']]
            );

            $functions[$role] = BusinessFunction::updateOrCreate(
                ['code' => $definition['code']],
                ['name' => $definition['name'], 'description' => "Funzione RACI ({$role}) dello Scadenziario COGE: tipo dipendente \"{$definition['type']}\"."]
            );
        }

        return $functions;
    }

    /**
     * Un template per ogni severity che prevede un'email (ok no), con tono e urgenza crescenti.
     * Il seeder riallinea sempre il testo: eventuali modifiche da pannello vanno rifatte dopo averlo rieseguito.
     *
     * @param  array{command: string, name: string, template: string, subject: string, intro: string}  $check
     */
    private function seedEmailTemplates(array $check): void
    {
        $tones = [
            Severity::Regular->value => ['prefix' => 'Segnalazione', 'text' => 'Segnalazione ordinaria: da verificare nei prossimi giorni.'],
            Severity::Warning->value => ['prefix' => 'Attenzione', 'text' => 'Attenzione: la situazione richiede un intervento a breve.'],
            Severity::Alert->value => ['prefix' => 'ALLERTA', 'text' => 'ALLERTA: la situazione richiede un intervento immediato.'],
        ];

        foreach ([Severity::Regular, Severity::Warning, Severity::Alert] as $severity) {
            $tone = $tones[$severity->value];

            EmailTemplate::updateOrCreate(
                ['code' => $check['template'], 'severity' => $severity->value],
                [
                    'name' => "{$check['name']} — {$severity->label()}",
                    'subject' => "{$tone['prefix']} Scadenziario COGE: {$check['subject']} ({value})",
                    'body' => "{$tone['text']}\n\n{$check['intro']}\n\nValore rilevato: {value}\nSeverity: {severity}\n\nDettaglio:\n{details}\n\nQuesta email è stata generata automaticamente da UnicoBPM (processo Scadenziario COGE).",
                    'placeholders' => ['{value}', '{severity}', '{details}'],
                    'is_active' => true,
                ]
            );
        }
    }
}
