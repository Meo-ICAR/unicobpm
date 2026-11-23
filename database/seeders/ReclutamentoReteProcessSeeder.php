<?php

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\DocumentType;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessState;
use App\Models\ProcessTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Processo di reclutamento e onboarding della rete commerciale (mediatore creditizio), dal «Modello operativo per l'onboarding della rete
 * agenziale» (versione consolidata del 22/11/2026, che sostituisce l'allegato sintetico «Workflow e RACI» in bozza):
 * 14 fasi (= passi del processo), RACI per fase, gate bloccanti G1-G8 (checklist), schede dati e documenti per fase, regola
 * recruiting@races.it sempre in CC (presidio condiviso) e sollecito automatico dopo 5 giorni dei documenti preliminari mancanti.
 *
 * Sostituisce i processi di onboarding precedenti (AGENT_ONBOARDING e PRC-ONBOARD-COLL), che vengono disattivati.
 * Idempotente: rieseguirlo aggiorna fasi, RACI, azioni e checklist senza duplicarli. Le persone non si assegnano qui: i ruoli
 * (employee_types) li ricoprono i dipendenti con `employee_roles` (organigramma).
 */
class ReclutamentoReteProcessSeeder extends Seeder
{
    public const CODE = 'PRC-RECLUTAMENTO-RETE';

    /** Processi di onboarding sostituiti da questo. */
    public const SOSTITUITI = ['AGENT_ONBOARDING', 'PRC-ONBOARD-COLL'];

    /** Ordine delle colonne della RACI consolidata. */
    private const FUNZIONI = ['OUT', 'MKT', 'ORG', 'COM', 'AM', 'SEG', 'CMP', 'LEG', 'DIR', 'AMM', 'BOC', 'BOQ', 'IT', 'REC'];

    /** Funzione => [codice ufficio (employee_types.code), nome, macro area, tipo, esternalizzabile, esterno, descrizione]. */
    private const RUOLI = [
        'OUT' => ['RECL-OUT', 'Outsourcer recruiting', 'Business / Commerciale', 'Operativa', 'yes', true, 'Bankeo / Move S.r.l. (referente Antonio Perreca): campagne, candidature, primo contatto e appuntamenti.'],
        'MKT' => ['RECL-MKT', 'Marketing e referente outsourcing', 'Business / Commerciale', 'Operativa', 'no', false, 'Ufficio Marketing e coordinamento del recruiting in outsourcing (Davide Bastione).'],
        'ORG' => ['ORG-RU', 'Organizzazione e risorse umane', 'Supporto', 'Supporto', 'no', false, null],
        'COM' => ['COMMERCIALE', 'Area Commerciale', 'Business / Commerciale', 'Operativa', 'no', false, null],
        'AM' => ['RECL-AM', 'Area Manager e supporto mutui', 'Business / Commerciale', 'Operativa', 'no', false, 'Area Manager territoriali e supporto Divisione Mutui: scouting, colloqui, formazione mutui e ramp-up.'],
        'SEG' => ['SEGR-RETE', 'Segreteria di rete', 'Supporto', 'Operativa', 'no', false, null],
        'CMP' => ['COMPLIANCE', 'Compliance', 'Controlli (II Livello)', 'Controllo', 'yes', false, null],
        'LEG' => ['LEGAL', 'Legale', 'Supporto', 'Supporto', 'yes', false, null],
        'DIR' => ['AD', 'Amministratore delegato', 'Governance', 'Strategica', 'no', false, null],
        'AMM' => ['AFFARI', 'Affari societari e contabilità', 'Supporto', 'Supporto', 'yes', false, null],
        'BOC' => ['RECL-BOC', 'Coordinatore back office', 'Business / Commerciale', 'Operativa', 'no', false, 'Coordinamento del back office di prodotto (esclusi Quinto/TFS): formazione e qualità operativa.'],
        'BOQ' => ['RECL-BO-QTFS', 'Back office Quinto e TFS', 'Business / Commerciale', 'Operativa', 'no', false, 'Responsabile BO Quinto/TFS: formazione e monitoraggio dei prodotti Quinto e TFS.'],
        'IT' => ['IT', 'IT e comunicazione digitale', 'Supporto', 'Supporto', 'partial', false, null],
        'REC' => ['RECL-PRESIDIO', 'Presidio recruiting', 'Supporto', 'Supporto', 'no', false, 'Presidio condiviso recruiting@races.it: riceve in CC ogni comunicazione, richiesta, risposta, verifica e approvazione del recruiting e dell\'onboarding.'],
    ];

    /** Attività della RACI consolidata, nell'ordine delle colonne FUNZIONI. A* = Direzione accountable solo nei casi strategici o fuori standard. */
    private const RACI = [
        'Definizione profili' => 'I C C AR C I I I I I I I I I',
        'Pianificazione campagne' => 'R AR C C I I I I I I I I I I',
        'Gestione campagne e landing' => 'AR C I I I I C I I I I I C I',
        'Primo contatto e dati minimi' => 'AR C I I I I I I I I I I I I',
        'Fissazione colloquio' => 'R A I C C I I I I I I I I I',
        'Controllo SLA e qualità candidature' => 'C AR C C I I I I I I I I I I',
        'Primo colloquio' => 'I I I A R I C I I I I I I I',
        'Apertura pre-onboarding' => 'I I I AR I R I I I I I I I I',
        'Raccolta documenti preliminari' => 'I I I A I R C I I I I I I I',
        'Check OAM e IVASS/RUI' => 'I I C I I R A I I I I I I I',
        'Report Cerved e chiarimenti' => 'I I I C I R A C A* I I I I I',
        'Condizioni standard' => 'I I I AR I I C I I C I I I I',
        'LOI standard' => 'I I I R I R C I A I I I I I',
        'Varianti LOI' => 'I I C R I R C R A C I I I I',
        'Due diligence definitiva' => 'I I C I I R A C A* I I I I I',
        'Contratto e adempimenti' => 'I I I I I R C C A R I I I I',
        'Piano formativo' => 'I I I C I R A I I I R R I I',
        'Formazione Mutui' => 'I I I A R I C I I I R I I I',
        'Formazione altri prodotti' => 'I I I I I I C I I I AR I I I',
        'Formazione Quinto/TFS' => 'I I I I I I C I I I I AR I I',
        'Provisioning e profilazioni' => 'I I I I I C A I I I C C R I',
        'Autorizzazione Go Live' => 'I I C R I R A I I I I I C I',
        'Ramp-up commerciale' => 'I I I A R I I I I I C C I I',
        'Monitoraggio operativo ordinario' => 'I I I C I I C I I I AR I I I',
        'Monitoraggio Quinto/TFS' => 'I I I C I I C I I I I AR I I',
        'Valutazione a 90 giorni' => 'I I I AR R C C I A* I R R I I',
        'Reporting recruiting e conversioni' => 'R AR C C I I I I I I I I I I',
        'Riesame Bankeo/Move' => 'C R A C I I C C I I I I I I',
        'Offboarding' => 'I I I R I R A C A* R R R R I',
    ];

    /** Gate bloccanti: [passaggio, accountable (funzione), nome dell'accountable, condizioni minime]. */
    private const GATE = [
        'G1' => ['Trasferimento a Races', 'MKT', 'Davide Bastione', ['Candidatura tracciata (codice REC)', 'Informativa privacy resa al candidato', 'Dati minimi acquisiti', 'Colloquio fissato']],
        'G2' => ['Pre-onboarding', 'COM', 'Riccardo Voce', ['Colloquio concluso', 'Reciproco interesse registrato nella scheda esito']],
        'G3' => ['LOI', 'DIR', 'Sergio Bracale', ['Check preliminari OAM, IVASS/RUI e Cerved completati', 'Eventuali criticità disciplinate con condizioni sospensive']],
        'G4' => ['Contratto', 'CMP', 'Francesco Maiello', ['Due diligence definitiva completata', 'Clearance definitiva Compliance rilasciata']],
        'G5' => ['Regolarizzazione', 'CMP', 'Francesco Maiello', ['Contratto sottoscritto', 'Adempimenti OAM/RUI completati']],
        'G6' => ['Abilitazione', 'CMP', 'Francesco Maiello', ['Formazione obbligatoria completata', 'Test superati']],
        'G7' => ['Go Live', 'CMP', 'Francesco Maiello', ['Utenze create', 'Profilazioni assegnate', 'Back office e tutor assegnati', 'Checklist Go Live completa']],
        'G8' => ['Conferma', 'COM', 'Riccardo Voce', ['Ramp-up di 90 giorni concluso', 'Esito positivo (check 15/30/60/90)']],
    ];

    /** Stati applicativi (21) con il codice del passo a cui appartengono (ordine 1-14). */
    private const STATI = [
        1 => ['01', 'Candidatura ricevuta', 'OUT/MKT', 2], 2 => ['02', 'Primo contatto in corso', 'OUT', 3], 3 => ['03', 'Colloquio programmato', 'OUT/MKT', 4],
        4 => ['04', 'Colloquio effettuato', 'COM', 5], 5 => ['05', 'Archiviato senza seguito', 'COM', 5], 6 => ['06', 'Pre-onboarding aperto', 'COM/SEG', 6],
        7 => ['07', 'Documenti preliminari attesi', 'SEG', 6], 8 => ['08', 'Check preliminari in corso', 'SEG/CMP', 7], 9 => ['09', 'Chiarimenti richiesti', 'CMP/SEG', 7],
        10 => ['10', 'LOI autorizzata', 'DIR', 8], 11 => ['11', 'LOI in firma', 'SEG', 8], 12 => ['12', 'LOI sottoscritta', 'SEG', 8],
        13 => ['13', 'Due diligence definitiva', 'CMP/SEG', 9], 14 => ['14', 'Contratto in firma', 'SEG/DIR', 10], 15 => ['15', 'Adempimenti OAM/RUI', 'SEG/CMP', 10],
        16 => ['16', 'Formazione e provisioning', 'CMP/BO/IT', 11], 17 => ['17', 'Pronto per Go Live', 'SEG', 12], 18 => ['18', 'Operativo in ramp-up', 'COM/BO', 13],
        19 => ['19', 'Operativo confermato', 'COM', 13], 20 => ['20', 'Sospeso', 'CMP/COM', 14], 21 => ['21', 'Cessato/offboarding', 'SEG/IT', 14],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->disattivaPrecedenti();

            $ruoli = $this->ruoli();
            $process = Process::updateOrCreate(['code' => self::CODE], [
                'name' => 'Reclutamento e onboarding della rete commerciale (mediatore creditizio)',
                'description' => $this->descrizioneProcesso(),
                'version' => 1,
                'is_active' => true,
                'is_periodic' => false,
            ]);

            $this->checklistGate();

            foreach ($this->fasi() as $indice => $fase) {
                $this->fase($process, $indice + 1, $fase, $ruoli);
            }

            $process->tasks()->where('ordine', '>', count($this->fasi()) * 10)->delete();

            $this->stati($process);
        });
    }

    /** Stati applicativi (21) con la fase di appartenenza; finali: 05, 19, 21; di eccezione: 20 (sospeso). */
    private function stati(Process $process): void
    {
        $kept = [];

        foreach (self::STATI as $numero => [$codice, $nome, $owner, $fase]) {
            $kept[] = $codice;

            ProcessState::updateOrCreate(['process_id' => $process->id, 'code' => $codice], [
                'process_task_id' => $process->tasks()->where('ordine', $fase * 10)->value('id'),
                'name' => $nome,
                'owner' => $owner,
                'ordine' => $numero,
                'is_final' => in_array($codice, ['05', '19', '21'], true),
                'is_exception' => $codice === '20',
            ]);
        }

        ProcessState::where('process_id', $process->id)->whereNotIn('code', $kept)->delete();
    }

    /** Disattiva i processi di onboarding precedenti, senza cancellarli (restano per lo storico). */
    private function disattivaPrecedenti(): void
    {
        Process::whereIn('code', self::SOSTITUITI)->update(['is_active' => false]);
    }

    /** @return array<string, EmployeeType> funzione (sigla della RACI) => ruolo */
    private function ruoli(): array
    {
        $ruoli = [];

        foreach (self::RUOLI as $funzione => [$codice, $nome, $macro, $tipo, $esternalizzabile, $esterno, $descrizione]) {
            $attributi = ['name' => $nome, 'macro_area' => $macro, 'type' => $tipo, 'outsourcable_status' => $esternalizzabile];

            // Per i ruoli già esistenti (compliance, commerciale, IT...) non si cambia nulla; si creano solo quelli nuovi del recruiting.
            if (str_starts_with($codice, 'RECL-')) {
                $attributi += ['description' => $descrizione, 'is_external' => $esterno, 'companytype' => 'FINANCE', 'managed_by_code' => 'COMMERCIALE'];

                if ($funzione === 'REC') {
                    $attributi['email'] = 'recruiting@races.it';
                }

                $ruoli[$funzione] = EmployeeType::office($codice, $attributi);
            } else {
                $ruoli[$funzione] = EmployeeType::where('code', $codice)->first() ?? EmployeeType::office($codice, $attributi);
            }
        }

        return $ruoli;
    }

    /** Checklist dei gate G1-G8: le condizioni minime del modello operativo più l'approvazione dell'accountable. */
    private function checklistGate(): void
    {
        foreach (self::GATE as $gate => [$passaggio, , $accountable, $condizioni]) {
            $codice = 'chk_recl_'.strtolower($gate);
            $checklist = Checklist::updateOrCreate(['code' => $codice], [
                'name' => "Gate {$gate} – {$passaggio}",
                'description' => "Gate bloccante {$gate} (passaggio: {$passaggio}) del processo di reclutamento della rete. Accountable: {$accountable}.",
                'is_active' => true,
            ]);

            $voci = array_map(fn (string $c): array => [$c, "Soddisfatto: {$c}?"], $condizioni);
            $voci[] = ["Approvazione {$accountable}", "Il gate {$gate} ({$passaggio}) è approvato da {$accountable}?"];

            foreach ($voci as $i => [$nome, $domanda]) {
                ChecklistItem::updateOrCreate(['checklist_id' => $checklist->id, 'item_code' => strtolower($gate).'_'.($i + 1)], [
                    'ordine' => ($i + 1) * 10, 'name' => $nome, 'question' => $domanda, 'type' => 'boolean', 'is_required' => true,
                ]);
            }
        }
    }

    /**
     * @param  array{nome: string, descr: string, acc: string, attori: string, attivita: list<string>, items: list<array>, sla: array, gate?: list<string>}  $fase
     * @param  array<string, EmployeeType>  $ruoli
     */
    private function fase(Process $process, int $numero, array $fase, array $ruoli): void
    {
        $ordine = $numero * 10;
        $stati = collect(self::STATI)->filter(fn (array $s): bool => $s[3] === $numero)->map(fn (array $s): string => "{$s[0]} {$s[1]} ({$s[2]})")->implode('; ');

        $task = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => $ordine], [
            'code' => strtolower(self::CODE).'-'.$numero,
            'name' => $fase['nome'],
            'description' => $fase['descr']."\nAttori: {$fase['attori']}.\nAccountable: ".self::RUOLI[$fase['acc']][1]
                .($stati !== '' ? ".\nStati applicativi: {$stati}" : '')
                ."\nRegola inderogabile: ogni comunicazione, richiesta, risposta, verifica e approvazione riporta SEMPRE recruiting@races.it in CC o come destinatario (codice REC nell'oggetto; per i dati sensibili solo notifica e link al fascicolo riservato).",
            'employee_type_id' => $ruoli[$fase['acc']]->id,
            'has_reminders' => true,
            'reminder_interval_days' => $fase['sla'][0],
            'max_reminders' => $fase['sla'][1],
            'escalation_rules' => [
                ['level' => 1, 'action' => 'notify_assignee', 'delay_hours' => $fase['sla'][2]],
                ['level' => 2, 'action' => 'notify_manager', 'delay_hours' => $fase['sla'][2] * 2],
            ],
            'is_active' => true,
        ]);

        $raci = $this->raciDellaFase($fase['attivita'], $fase['acc']);

        foreach ($raci as $funzione => [$ruolo, $nota]) {
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $ruoli[$funzione]->id], ['raci_role' => $ruolo]);

            if ($nota !== null) {
                DB::table('process_task_raci')->where('process_task_id', $task->id)->where('employee_type_id', $ruoli[$funzione]->id)->update(['notes' => $nota]);
            }
        }

        $task->raciAssignments()->whereNotIn('employee_type_id', collect($raci)->keys()->map(fn (string $f): int => $ruoli[$f]->id))->delete();

        $this->azioni($task, $fase);
    }

    /**
     * RACI di una fase dalle attività che la compongono: l'accountable della fase è A; chi è R (o accountable di una sotto-attività) è R;
     * chi è consultato C; la Direzione con A* resta C con nota; gli altri I.
     *
     * @param  list<string>  $attivita
     * @return array<string, array{0: string, 1: ?string}> funzione => [ruolo, nota]
     */
    private function raciDellaFase(array $attivita, string $accountable): array
    {
        $out = [];

        foreach (self::FUNZIONI as $i => $funzione) {
            $valori = array_map(fn (string $a): string => explode(' ', self::RACI[$a])[$i], $attivita);
            $nota = null;

            $ruolo = match (true) {
                $funzione === $accountable => 'A',
                (bool) array_intersect($valori, ['R', 'AR', 'A']) => 'R',
                in_array('A*', $valori, true) => 'C',
                in_array('C', $valori, true) => 'C',
                default => 'I',
            };

            if (in_array('A*', $valori, true) && $funzione !== $accountable) {
                $nota = 'A* – Direzione accountable solo nei casi strategici, fuori standard o con criticità rilevanti.';
            }

            if ($funzione === 'REC') {
                $nota = 'Presidio condiviso recruiting@races.it: in CC o destinatario di ogni comunicazione.';
            }

            $out[$funzione] = [$ruolo, $nota];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $fase */
    private function azioni(ProcessTask $task, array $fase): void
    {
        $azioni = $fase['items'];

        // Gate che chiudono la fase (bloccanti: si passa alla fase successiva solo con tutte le azioni obbligatorie completate).
        foreach ($fase['gate'] ?? [] as $gate) {
            $azioni[] = ["Gate {$gate} – ".self::GATE[$gate][0]." (accountable: ".self::GATE[$gate][2].')', 'fill_checklist', null, 'chk_recl_'.strtolower($gate)];
        }

        $kept = [];

        foreach ($azioni as $indice => $azione) {
            [$nome, $tipo, $documento, $checklist] = $azione + [null, null, null, null];
            $ordine = ($indice + 1) * 10;
            $kept[] = $ordine;

            $task->processTaskItems()->updateOrCreate(['process_task_id' => $task->id, 'ordine' => $ordine], [
                'name' => $nome,
                'action_type' => $tipo,
                'document_type_id' => $this->tipoDocumento($documento),
                'checklist_id' => $checklist ? Checklist::where('code', $checklist)->value('id') : null,
                'is_required' => $azione[4] ?? true,
                'config' => null,
                'is_active' => true,
            ]);
        }

        $task->processTaskItems()->whereNotIn('ordine', $kept)->delete();
    }

    /** Tipo documento esistente dal nome (le prime lettere bastano), null se non c'è: l'azione resta un caricamento generico. */
    private function tipoDocumento(?string $nome): ?int
    {
        return $nome === null ? null : DocumentType::query()->where('name', 'like', $nome.'%')->orderBy('id')->value('id');
    }

    private function comunicazione(): array
    {
        return ['Comunicazione dell\'esito con recruiting@races.it in CC (codice REC nell\'oggetto)', 'text_input'];
    }

    /**
     * Le 14 fasi del workflow end-to-end con attori, attività RACI, dati da registrare e documenti/evidenze di ciascuna scheda (par. 3 e 8).
     *
     * @return list<array<string, mixed>>
     */
    private function fasi(): array
    {
        $com = $this->comunicazione();

        return [
            [
                'nome' => 'Brief recruiting',
                'descr' => 'Profilo, territorio, prodotto e campagna. Output/gate: brief approvato.',
                'acc' => 'COM', 'attori' => 'Area Commerciale (Riccardo Voce), Marketing (Davide Bastione), Bankeo/Move', 'sla' => [2, 3, 72],
                'attivita' => ['Definizione profili', 'Pianificazione campagne'],
                'items' => [
                    ['Brief recruiting: profili, territorio, prodotto e campagna', 'text_input'],
                    ['Brief approvato (Area Commerciale e Marketing)', 'text_input'],
                    $com,
                ],
            ],
            [
                'nome' => 'Campagna e candidatura',
                'descr' => 'Landing, informativa, CV, fonte, UTM. Attori: Bankeo/Move S.r.l. (Antonio Perreca), Davide Bastione. Output: candidatura tracciata.',
                'acc' => 'MKT', 'attori' => 'Bankeo/Move S.r.l. (Antonio Perreca), Davide Bastione', 'sla' => [2, 3, 48],
                'attivita' => ['Gestione campagne e landing', 'Controllo SLA e qualità candidature'],
                'items' => [
                    ['Codice REC-AAAA-NNNN e fascicolo creati alla prima candidatura', 'text_input'],
                    ['Dati candidatura: canale, campagna, UTM, territorio, prodotto, recapiti, esperienza dichiarata', 'text_input'],
                    ['Brief approvato, copy e landing approvati, informativa privacy pubblicata', 'text_input'],
                    ['Curriculum vitae / scheda candidato acquisita', 'document_upload', 'Curriculum'],
                    ['Riepilogo e-mail con recruiting@races.it in CC (Landing/CRM Bankeo)', 'text_input'],
                ],
            ],
            [
                'nome' => 'Primo contatto',
                'descr' => 'Tentativi, dati minimi e interesse (SLA: primo contatto entro 2 giorni, almeno 3 tentativi). Output: candidato raggiunto.',
                'acc' => 'MKT', 'attori' => 'Bankeo/Move S.r.l. (Antonio Perreca)', 'sla' => [1, 3, 48],
                'attivita' => ['Primo contatto e dati minimi'],
                'items' => [
                    ['Registro tentativi di contatto: data, ora, esito (almeno 3 tentativi)', 'text_input'],
                    ['Scheda preliminare: interesse, referente commerciale e dati minimi', 'text_input'],
                    $com,
                ],
            ],
            [
                'nome' => 'Appuntamento',
                'descr' => 'Assegnazione e invito calendario (Outlook/Teams) con codice REC nell\'oggetto. Output/gate: colloquio fissato (G1).',
                'acc' => 'MKT', 'attori' => 'Bankeo/Move S.r.l., Marketing (Davide Bastione), Area Commerciale', 'sla' => [1, 3, 24],
                'attivita' => ['Fissazione colloquio'],
                'items' => [
                    ['Appuntamento: data, ora, referente commerciale, invito Outlook/Teams con codice REC', 'text_input'],
                    ['E-mail/invito con recruiting@races.it in CC; esito telefonata riepilogato', 'text_input'],
                ],
                'gate' => ['G1'],
            ],
            [
                'nome' => 'Colloquio commerciale',
                'descr' => 'Esperienza, volumi, territorio e vincoli. Esito entro 1 giorno: stop, approfondimento o pre-onboarding (G2).',
                'acc' => 'COM', 'attori' => 'Riccardo Voce, Paolo Sajeva, Area Manager', 'sla' => [1, 3, 24],
                'attivita' => ['Primo colloquio'],
                'items' => [
                    ['Dati colloquio: esperienza, prodotti, territorio, volumi, rapporti, aspettative, criticità', 'text_input'],
                    ['CV del candidato', 'document_upload', 'Curriculum'],
                    ['Scheda esito colloquio e nota di reciproco interesse', 'text_input'],
                    ['Esito: stop (archivio senza seguito), approfondimento o pre-onboarding', 'text_input'],
                    ['Scheda inviata a Segreteria con recruiting@races.it in CC (entro 1 giorno)', 'text_input'],
                ],
                'gate' => ['G2'],
            ],
            [
                'nome' => 'Pre-onboarding e documenti preliminari',
                'descr' => 'Fascicolo preliminare con i documenti utili alla lettera di intenti. Sollecito automatico dopo 5 giorni per i documenti mancanti.',
                'acc' => 'COM', 'attori' => 'Riccardo Voce, Roberto Perna (Segreteria di Rete)', 'sla' => [5, 3, 120],
                'attivita' => ['Apertura pre-onboarding', 'Raccolta documenti preliminari'],
                'items' => [
                    ['Scheda dati unica del candidato (anagrafica, profilo, rapporti in essere, requisiti, economico, controlli, privacy)', 'text_input'],
                    ['Documento di identità e codice fiscale', 'document_upload', 'Documento identificativo'],
                    ['Curriculum vitae', 'document_upload', 'Curriculum'],
                    ['Partita IVA / visura camerale', 'document_upload', 'Visura camerale'],
                    ['Titolo di studio, prova OAM o esonero, attestati di formazione', 'document_upload', 'Titolo di Studio'],
                    ['Mandati, vincoli e rapporti in essere (esclusive, preavvisi, non concorrenza, restituzioni, liberazione)', 'document_upload', 'Liberatoria'],
                    ['Volumi dichiarati ed evidenze a campione (prodotti, territorio, owner, scadenza)', 'text_input'],
                    ['E-mail ufficiale con recruiting@races.it in CC; upload protetto per gli allegati', 'text_input'],
                ],
            ],
            [
                'nome' => 'Check preliminari (OAM, IVASS/RUI, Cerved)',
                'descr' => 'Verifiche prima della LOI con chiarimento delle anomalie. Esito: Regolare; Regolare con prescrizioni; Da chiarire; Sospeso; Ostativo. Target: check entro 5 giorni dal fascicolo completo (G3).',
                'acc' => 'CMP', 'attori' => 'Roberto Perna, Francesco Maiello, Gioia Quarto se necessario', 'sla' => [2, 3, 96],
                'attivita' => ['Check OAM e IVASS/RUI', 'Report Cerved e chiarimenti'],
                'items' => [
                    ['Evidenza consultazione elenco OAM (fonte, data, operatore, esito)', 'document_upload'],
                    ['Evidenza IVASS/RUI (fonte, data, operatore, esito)', 'document_upload', 'Modulistica iscrizione IVASS'],
                    ['Report Cerved (Basic Note)', 'document_upload', 'Basic Note'],
                    ['Anomalie, richieste e risposte di chiarimento, prescrizioni', 'text_input', null, null, false],
                    ['Esito check: Regolare / Regolare con prescrizioni / Da chiarire / Sospeso / Ostativo', 'text_input'],
                    ['Fascicolo riservato; e-mail di esito con recruiting@races.it in CC (solo link e esito, mai dati sensibili nel corpo)', 'text_input'],
                ],
                'gate' => ['G3'],
            ],
            [
                'nome' => 'Lettera di intenti (LOI standard o variante)',
                'descr' => 'LOI standard (nessun passaggio legale ordinario) o variante validata dal Legale. La LOI non abilita l\'operatività: resta subordinata a due diligence, contratto, OAM/RUI, formazione e provisioning. Se è selezionato «Variante rispetto allo standard» la richiesta va in automatico a Gioia Quarto (Legale).',
                'acc' => 'DIR', 'attori' => 'Riccardo Voce, Roberto Perna, Gioia Quarto (varianti), Francesco Maiello, Sergio Bracale', 'sla' => [2, 3, 72],
                'attivita' => ['Condizioni standard', 'LOI standard', 'Varianti LOI'],
                'items' => [
                    ['Scheda condizioni: ruolo, prodotti, territorio, compensi, target, anticipi, lead, sospensive', 'text_input'],
                    ['Variante rispetto allo standard? (sì/no; se sì motivazione, rischio, impatto)', 'text_input'],
                    ['Verifica Compliance sulla scheda condizioni / sul modello', 'text_input'],
                    ['Parere Legale (solo varianti: bozza comparata, tabella varianti, parere Gioia Quarto)', 'text_input', null, null, false],
                    ['LOI sottoscritta (catena unica versionata con recruiting@races.it in CC)', 'document_upload', 'Lettera di'],
                ],
            ],
            [
                'nome' => 'Due diligence definitiva',
                'descr' => 'Requisiti, onorabilità, incompatibilità e cessazioni. Output: clearance definitiva (nessun contratto senza clearance Compliance).',
                'acc' => 'CMP', 'attori' => 'Roberto Perna, Francesco Maiello, Gioia Quarto per temi giuridici', 'sla' => [2, 3, 96],
                'attivita' => ['Due diligence definitiva'],
                'items' => [
                    ['Titolo di studio', 'document_upload', 'Titolo di Studio'],
                    ['Prova valutativa OAM o evidenza di esonero', 'document_upload', 'Esito prova valutativa OAM'],
                    ['Formazione e attestati', 'document_upload', 'Attestato Formazione OAM'],
                    ['Casellario giudiziale e carichi pendenti', 'document_upload', 'Casellario'],
                    ['Dichiarazione sostitutiva di onorabilità', 'document_upload', 'Dichiarazione sostitutiva'],
                    ['Incompatibilità, cessazioni e patti di non concorrenza (requisito, documento, emittente, data, scadenza, esito, validatore)', 'text_input'],
                    ['Checklist requisiti OAM (iscrizione, onorabilità, professionalità, formazione, PEC, RC)', 'fill_checklist', null, 'chk_oam_01'],
                    ['Notifica con recruiting@races.it in CC; repository riservato', 'text_input'],
                ],
                'gate' => ['G4'],
            ],
            [
                'nome' => 'Contratto e adempimenti',
                'descr' => 'Contratto individuale, allegato economico, riservatezza, IBAN e adempimenti OAM/RUI. Nessuna abilitazione senza contratto e OAM/RUI completati (G5).',
                'acc' => 'DIR', 'attori' => 'Roberto Perna, Sergio Bracale, Gennaro Muscetti, Francesco Maiello', 'sla' => [2, 3, 96],
                'attivita' => ['Contratto e adempimenti'],
                'items' => [
                    ['Dati contratto: decorrenza, prodotti, regime fiscale, provvigioni, stato OAM/RUI', 'text_input'],
                    ['Contratto individuale firmato', 'document_upload', 'Contratto di agenzia'],
                    ['Allegato economico firmato', 'document_upload'],
                    ['Privacy e riservatezza', 'document_upload', 'Modulo Privacy'],
                    ['IBAN e dati fiscali (regime fiscale, ritenuta) per le provvigioni', 'text_input'],
                    ['Posizione Enasarco (agenti)', 'document_upload', 'Enasarco', null, false],
                    ['Ricevute OAM/RUI e relativo esito', 'document_upload'],
                    ['Firma elettronica/PEC; ricevute e esito con recruiting@races.it in CC', 'text_input'],
                ],
                'gate' => ['G5'],
            ],
            [
                'nome' => 'Formazione e provisioning',
                'descr' => 'Moduli, test, account e profilazioni. Target: provisioning entro 3 giorni dalla clearance. Nessun Go Live senza formazione, test, utenze e checklist complete (G6, G7).',
                'acc' => 'CMP', 'attori' => 'Francesco Maiello, Davide Bastione, Raffaele Gravina, IT, Roberto Perna', 'sla' => [2, 3, 72],
                'attivita' => ['Piano formativo', 'Formazione Mutui', 'Formazione altri prodotti', 'Formazione Quinto/TFS', 'Provisioning e profilazioni'],
                'items' => [
                    ['Piano formativo (moduli, data, formatore, prodotto)', 'text_input'],
                    ['Attestati e test superati', 'document_upload', 'Attestato Formazione OAM'],
                    ['Ticket IT: utenza, MFA, profilazioni e test accessi (mai password via e-mail)', 'text_input'],
                    ['Assegnazione Back Office e tutor', 'text_input'],
                    ['Chiusura via e-mail con recruiting@races.it in CC (LMS/Teams e ticket IT)', 'text_input'],
                ],
                'gate' => ['G6'],
            ],
            [
                'nome' => 'Go Live',
                'descr' => 'Checklist e comunicazione formale. E-mail «GO LIVE AUTORIZZATO» con oggetto standard e recruiting@races.it in CC. Output: operatività autorizzata.',
                'acc' => 'CMP', 'attori' => 'Francesco Maiello, Roberto Perna, Riccardo Voce', 'sla' => [1, 3, 48],
                'attivita' => ['Autorizzazione Go Live'],
                'items' => [
                    ['Dati Go Live: data, prodotti, territorio, limiti, tutor, prescrizioni', 'text_input'],
                    ['Checklist Go Live firmata e clearance', 'document_upload'],
                    ['Esiti utenze verificati', 'text_input'],
                    ['Comunicazione «GO LIVE AUTORIZZATO» a tutti i soggetti, con recruiting@races.it in CC', 'text_input'],
                ],
                'gate' => ['G7'],
            ],
            [
                'nome' => 'Ramp-up 90 giorni',
                'descr' => 'Check a 15, 30, 60 e 90 giorni su KPI, qualità, produzione, lead e anomalie. Output: conferma, piano correttivo o sospensione (G8).',
                'acc' => 'COM', 'attori' => 'Riccardo Voce, Paolo Sajeva/AM, Davide Bastione o Raffaele Gravina, Compliance', 'sla' => [7, 4, 168],
                'attivita' => ['Ramp-up commerciale', 'Monitoraggio operativo ordinario', 'Monitoraggio Quinto/TFS', 'Valutazione a 90 giorni', 'Reporting recruiting e conversioni', 'Riesame Bankeo/Move'],
                'items' => [
                    ['Check a 15 giorni (KPI, qualità, produzione, lead, anomalie, azioni, scadenze)', 'text_input'],
                    ['Check a 30 giorni', 'text_input'],
                    ['Check a 60 giorni', 'text_input'],
                    ['Check a 90 giorni', 'text_input'],
                    ['Report Back Office e piano correttivo (se serve)', 'document_upload', null, null, false],
                    ['Esito finale: Confermato / Piano correttivo / Limitato / Sospeso / Cessato', 'text_input'],
                    ['Teams + verbale con recruiting@races.it in CC (15/30/60/90)', 'text_input'],
                ],
                'gate' => ['G8'],
            ],
            [
                'nome' => 'Offboarding',
                'descr' => 'Solo in caso di sospensione o cessazione: revoche, riassegnazioni, cessazione OAM/RUI e chiusura economica. Output: rapporto chiuso. Revoca coordinata delle utenze.',
                'acc' => 'CMP', 'attori' => 'Compliance, Commerciale, Segreteria, IT, BO, Amministrazione', 'sla' => [2, 3, 72],
                'attivita' => ['Offboarding'],
                'items' => [
                    ['Dati: evento, efficacia, accessi, pratiche, OAM/RUI, importi, retention', 'text_input'],
                    ['Decisione di sospensione/cessazione', 'document_upload'],
                    ['Revoca accessi e riassegnazione pratiche (ticket IT, revoca alla decorrenza)', 'text_input'],
                    ['Cessazione OAM/RUI e chiusura economica', 'text_input'],
                    ['PEC/e-mail e ticket con recruiting@races.it sempre in CC', 'text_input'],
                ],
            ],
        ];
    }

    private function descrizioneProcesso(): string
    {
        $stati = collect(self::STATI)->map(fn (array $s): string => "{$s[0]} {$s[1]} (owner {$s[2]})")->implode("\n");
        $gate = collect(self::GATE)->map(fn (array $g, string $k): string => "{$k} {$g[0]} – {$g[2]}: ".implode('; ', $g[3]))->implode("\n");

        return <<<TXT
Reclutamento e onboarding della rete agenziale di Races Finance, dalla candidatura gestita da Bankeo/Move S.r.l. al Go Live, al ramp-up di 90 giorni e all'eventuale offboarding. Fonte: «Modello operativo per l'onboarding della rete agenziale», versione consolidata del 22/11/2026 (sostituisce l'allegato sintetico in bozza). Sostituisce i processi AGENT_ONBOARDING e PRC-ONBOARD-COLL.

Principi: Bankeo/Move genera e organizza la pipeline ma non decide idoneità, condizioni, LOI, contratto o Go Live; l'Area Commerciale decide il reciproco interesse e le condizioni, Compliance presidia requisiti e rischi, la Direzione approva gli impegni. La LOI non abilita l'operatività. Il fascicolo digitale è unico, versionato e accessibile per ruolo.

REGOLA INDEROGABILE: tutte le comunicazioni, richieste, risposte, informazioni, verifiche, approvazioni e adempimenti relativi al recruiting e all'onboarding riportano SEMPRE recruiting@races.it in CC o come destinatario. Per i documenti sensibili (Cerved, casellario, carichi pendenti) l'account riceve la notifica e il link al fascicolo riservato, non necessariamente l'allegato.

Convenzioni: codice pratica REC-AAAA-NNNN; oggetto e-mail [REC-AAAA-NNNN] COGNOME Nome | Fase | Azione | Scadenza; nome file AAAAMMGG_TipoDocumento_CognomeNome_vNN; cartelle 01_Candidatura, 02_Colloqui, 03_PreOnboarding, 04_Check, 05_LOI, 06_DueDiligence, 07_Contratto, 08_Formazione, 09_GoLive, 10_RampUp, 11_Offboarding. Esito controlli: Regolare; Regolare con prescrizioni; Da chiarire; Sospeso; Ostativo. Esito finale: Confermato; Piano correttivo; Limitato; Sospeso; Cessato.

Gate bloccanti:
{$gate}

Stati applicativi richiesti (21):
{$stati}

Automazioni richieste dal modello (da completare con IT/Operations): creazione automatica del codice REC e del fascicolo alla prima candidatura; notifica a recruiting@races.it per ogni cambio stato, documento ricevuto, approvazione e scadenza; sollecito dopo 5 giorni per documenti preliminari mancanti (fase 6: attivo); blocco LOI se i check preliminari non sono chiusi; routing a Gioia Quarto per le varianti LOI; blocco contratto senza clearance, provisioning senza contratto e OAM/RUI, Go Live senza formazione, test, utenze e checklist (gate G4-G7); agenda dei check a 15, 30, 60 e 90 giorni; revoca coordinata delle utenze. KPI: funnel candidature→conferme a 90 gg, tempo primo contatto ≤2 giorni, esito colloquio ≤1 giorno, check preliminari ≤5 giorni dal fascicolo completo, provisioning ≤3 giorni dalla clearance, revisione varianti LOI ≤3 giorni lavorativi, comunicazioni con recruiting@races.it in CC al 100%.

Da formalizzare (TBD del modello): owner IT/Operations, presidio Corrado, owner operativo RUI, repository definitivo, integrazione Bankeo, retention documentale, firma elettronica, deroghe ai gate, matrice accessi, data breach, SLA ed escalation finali, UAT e pilota. I tempi dei solleciti e delle escalation delle fasi sono provvisori (TBD-11).
TXT;
    }
}
