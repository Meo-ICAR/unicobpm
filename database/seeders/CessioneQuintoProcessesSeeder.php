<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use App\Models\EmailTemplate;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\ProcessTrigger;
use Illuminate\Database\Seeder;

/**
 * I sei processi della filiera di una Cessione del Quinto / Delegazione di pagamento (specifica `bpm_process_specification.md`):
 *
 *  1. PROC_ORIGINATION   – Origination e qualificazione lead
 *  2. PROC_ISTRUTTORIA   – Istruttoria e verifiche documentali
 *  3. PROC_DELIBERA      – Delibera bancaria e polizza assicurativa
 *  4. PROC_NOTIFICA      – Notifica dell'atto
 *  5. PROC_BENESTARE     – In attesa benestare ed estinzioni
 *  6. PROC_LIQUIDAZIONE  – Liquidazione e perfezionamento
 *
 * Come partono: ognuno aspetta un evento di UnicoFinance (POST /api/events). `lead.creato` avvia il primo; `pratica.stato_cambiato`
 * (dati: `stato` = codice in `pratica_stati`, `tipo_finanziamento`, `cliente_nome`, `cliente_email`, `cliente_codice_fiscale`, ...)
 * avvia gli altri quando la pratica entra nello stato indicato in {@see self::STATI}. Il primo processo si può avviare anche a mano.
 *
 * Ruoli: CdC = rete collaboratori (BUS-RETE-EXT), BO/Delegato = back office (BUS-BO), Resp. BO/Compliance = compliance (COMPLIANCE);
 * ATC/Ente e Partner/Banca sono controparti esterne e hanno un ruolo esterno ciascuno (ATC-ENTE, PARTNER-BANCA).
 *
 * Tempi: ogni passo ha la sua scadenza (SLA): scaduta, sollecito a chi ha in carico (livello 1) e poi al responsabile (livello 2, a
 * doppio della scadenza). Dove la specifica indica un'escalation verso la compliance, il passaggio è un «reassign» al suo ruolo.
 *
 * Il seeder è idempotente; i ruoli si assegnano da pannello nella scheda del dipendente o dell'esterno.
 */
class CessioneQuintoProcessesSeeder extends Seeder
{
    /** Stato di `pratica_stati` (tipo Cessione) che avvia ciascun processo: la specifica usa nomi propri, qui il codice reale. */
    public const STATI = [
        'PROC_ISTRUTTORIA' => 'RICHIESTA ISTRUTTORIA',   // era PROPOSAL_SIGNED
        'PROC_DELIBERA' => 'FASCICOLO COMPLETO',         // era APPROVED_INTERNAL
        'PROC_NOTIFICA' => 'ATTO FISSATO',               // era CONTRACT_SIGNED
        'PROC_BENESTARE' => 'IN ATTESA BENESTARE',       // era NOTIF_SENT
        'PROC_LIQUIDAZIONE' => 'RIENTRO BENESTARE',      // era BENESTARE_RECEIVED
    ];

    /** @var array<string, EmployeeType> */
    private array $roles = [];

    public function run(): void
    {
        $this->roles = [
            'CdC' => EmployeeType::office('BUS-RETE-EXT'),
            'BO' => EmployeeType::office('BUS-BO'),
            'COMPL' => EmployeeType::office('COMPLIANCE'),
            'ATC' => EmployeeType::office('ATC-ENTE', ['name' => 'ATC / Ente datore di lavoro', 'is_external' => true]),
            'BANCA' => EmployeeType::office('PARTNER-BANCA', ['name' => 'Partner / Banca', 'is_external' => true]),
        ];

        foreach ($this->definitions() as $definition) {
            $this->seedProcess($definition);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'code' => 'PROC_ORIGINATION',
                'name' => 'Origination e qualificazione lead',
                'description' => 'Interesse del cliente, fattibilità CQS o delegazione, calcolo della quota cedibile e proposta firmata.',
                'trigger' => ['event' => 'lead.creato', 'name' => 'Nuovo lead (CQS / delegazione)', 'conditions' => null],
                'steps' => [
                    ['STEP_ORIG_10', 'Inserimento del prospect e consenso privacy', 24, ['CdC', 'CdC', null, 'BO'], [
                        ['text', 'Dati del cliente registrati nel CRM (riferimento della scheda)'],
                        ['doc', 'Modulo Privacy', 'Consenso privacy firmato (estremi)'],
                    ]],
                    ['STEP_ORIG_20', 'Documenti precontrattuali e reddituali', 48, ['CdC', 'CdC', 'BO', null], [
                        ['doc', 'Modulo Privacy', 'Privacy firmata'],
                        ['text', 'SECCI firmato (estremi del documento)'],
                        ['doc', 'Contrato di Mediazione', 'Accordo di mediazione firmato'],
                        ['doc', 'Carta d\'Identità', 'Documento di identità'],
                        ['doc', 'Busta Paga / CU', 'Busta paga e CUD'],
                    ]],
                    ['STEP_ORIG_30', 'Calcolo della quota cedibile', 24, ['CdC', 'BO', 'ATC', null], [
                        ['text', 'Quota cedibile calcolata (€) e fonte: INPS, CreditoNet o strumento interno'],
                        ['text', 'Stipendio netto (€)'],
                        ['text', 'Errore tecnico del servizio (es. INPS 113, 209)? Se sì, indicare il codice e passare la pratica al BO', false],
                    ], [['level' => 2, 'delay_hours' => 24, 'action' => 'reassign', 'role' => 'BO']]],
                    ['STEP_ORIG_40', 'Proposta e firma', 72, ['CdC', 'CdC', null, 'BO'], [
                        ['mail', 'Invio della proposta al cliente', 'ORIG_PROPOSTA', ['to_email' => '{external_data.cliente_email}']],
                        ['text', 'Proposta firmata dal cliente (data) oppure rifiutata / nessuna risposta (motivo: la pratica si chiude come persa)'],
                    ]],
                ],
                'templates' => [
                    'ORIG_PROPOSTA' => ['Origination – proposta al cliente', 'Proposta di Finanziamento - {external_data.cliente_nome}',
                        'Gentile {external_data.cliente_nome}, in allegato la proposta per il suo finanziamento CQS. Rata mensile stimata: {external_data.quota_cedibile}€.'],
                ],
            ],
            [
                'code' => 'PROC_ISTRUTTORIA',
                'name' => 'Istruttoria e verifiche documentali',
                'description' => 'Verifica formale e di compliance dei documenti, impegni in busta paga, TFR e fondo pensione, prevenzione frodi.',
                'trigger' => ['event' => 'pratica.stato_cambiato', 'name' => 'Pratica in istruttoria', 'stato' => 'PROC_ISTRUTTORIA'],
                'steps' => [
                    ['STEP_ISTR_10', 'Verifica identità e firme', 24, ['BO', 'BO', 'CdC', null], [
                        ['text', 'Documento di identità valido e leggibile, firme coerenti? (sì / no con motivo: se no, sospendere e chiedere integrazione al CdC entro 48 ore)'],
                    ], [['level' => 2, 'delay_hours' => 48, 'action' => 'reassign', 'role' => 'COMPL']]],
                    ['STEP_ISTR_20', 'Impegni in busta paga', 24, ['BO', 'BO', null, 'CdC'], [
                        ['text', 'Censimento di CQS, delegazioni e pignoramenti rispetto ai limiti dello stipendio'],
                        ['text', 'Impegni validati (sì / no)'],
                    ]],
                    ['STEP_ISTR_30', 'TFR e fondo pensione', 48, ['BO', 'BO', 'ATC', 'CdC'], [
                        ['text', 'TFR disponibile e aggredibilità del fondo pensione (esito e importo)'],
                    ]],
                    ['STEP_ISTR_40', 'Prevenzione frodi e verifica del datore di lavoro', 24, ['BO', 'COMPL', 'ATC', null], [
                        ['text', 'Controlli di linea e telefonata al datore di lavoro (data, interlocutore, esito)'],
                        ['text', 'Sospetta alterazione o frode? (sì / no: se sì, bloccare la pratica e avvisare la compliance, chiusura come persa)'],
                    ]],
                ],
                'templates' => [],
            ],
            [
                'code' => 'PROC_DELIBERA',
                'name' => 'Delibera bancaria e polizza assicurativa',
                'description' => 'Trasmissione al partner, delibera di merito creditizio, polizza vita e impiego, firma del contratto.',
                'trigger' => ['event' => 'pratica.stato_cambiato', 'name' => 'Pratica approvata internamente', 'stato' => 'PROC_DELIBERA'],
                'steps' => [
                    ['STEP_DELI_10', 'Trasmissione del fascicolo al partner', 12, ['BO', 'BO', null, 'CdC'], [
                        ['mail', 'Invio al partner', 'DELI_TRASMISSIONE', ['target_business_function' => 'PARTNER-BANCA']],
                        ['text', 'Fascicolo caricato sul portale del partner (riferimento)'],
                    ]],
                    ['STEP_DELI_20', 'Delibera di merito creditizio', 72, ['BANCA', 'BANCA', 'BO', 'CdC'], [
                        ['doc', 'Delibera Istituto Finanziatore', 'Delibera del partner'],
                        ['text', 'Data della delibera. Se serve una deroga assuntiva, passare alla compliance per il comitato; se la banca rifiuta, la pratica si chiude come persa'],
                    ], [['level' => 2, 'delay_hours' => 72, 'action' => 'reassign', 'role' => 'COMPL']]],
                    ['STEP_DELI_30', 'Emissione della polizza', 48, ['BANCA', 'BANCA', 'BO', null], [
                        ['text', 'Numero di polizza (vita e rischio impiego)'],
                    ]],
                    ['STEP_DELI_40', 'Firma del contratto definitivo', 48, ['CdC', 'BO', 'BANCA', null], [
                        ['doc', 'Contratto Firmato', 'Contratto firmato dal cliente'],
                    ]],
                ],
                'templates' => [
                    'DELI_TRASMISSIONE' => ['Delibera – trasmissione al partner', 'Trasmissione pratica per delibera - {external_ref}',
                        'Trasmessa richiesta di delibera per la pratica {external_ref}, cliente {external_data.cliente_codice_fiscale}.'],
                ],
            ],
            [
                'code' => 'PROC_NOTIFICA',
                'name' => 'Notifica dell\'atto',
                'description' => 'Notifica del contratto firmato al datore di lavoro, a INPS / NoiPA e al fondo pensione.',
                'trigger' => ['event' => 'pratica.stato_cambiato', 'name' => 'Contratto firmato, da notificare', 'stato' => 'PROC_NOTIFICA'],
                'steps' => [
                    ['STEP_NOTI_10', 'Preparazione del plico di notifica', 12, ['BO', 'BO', null, null], [
                        ['text', 'Plico completo: contratto, SECCI, polizza e allegati A/B/E/B1'],
                    ]],
                    ['STEP_NOTI_20', 'Notifica all\'ATC / ente', 24, ['BO', 'BO', 'BANCA', 'ATC'], [
                        ['mail', 'PEC di notifica all\'ATC', 'NOTI_PEC_ATC', ['to_email' => '{external_data.atc_pec}']],
                        ['text', 'Canale e protocollo: telematico INPS / NoiPA, PEC, UNEP, raccomandata o consegna a mano'],
                        ['text', 'Scarto (NoiPA / RTS) o errore sui documenti? Se sì, firma PAdES del frontespizio integrativo di BO e compliance prima del nuovo invio'],
                    ]],
                    ['STEP_NOTI_30', 'Notifica al fondo pensione (se c\'è il TFR)', 24, ['BO', 'BO', null, 'ATC'], [
                        ['text', 'Notifica del vincolo sul TFR al fondo pensione (data e protocollo) oppure «non applicabile»'],
                    ]],
                ],
                'templates' => [
                    'NOTI_PEC_ATC' => ['Notifica – PEC all\'ATC', 'Notifica Contratto Cessione del Quinto - Dipendente {external_data.cliente_nome}',
                        'Spett.le ATC, si trasmette in allegato con valore legale la notifica del contratto di Cessione del Quinto relativo al dipendente {external_data.cliente_nome} (Prot. {external_ref}).'],
                ],
            ],
            [
                'code' => 'PROC_BENESTARE',
                'name' => 'In attesa benestare ed estinzioni',
                'description' => 'Rientro dell\'atto di benestare dal datore di lavoro e conteggi estintivi dei finanziamenti da estinguere.',
                'trigger' => ['event' => 'pratica.stato_cambiato', 'name' => 'Notifica inviata, in attesa di benestare', 'stato' => 'PROC_BENESTARE'],
                'steps' => [
                    ['STEP_BENE_10', 'Lettera di priorità e conteggi estintivi', 48, ['BO', 'BO', 'BANCA', 'ATC'], [
                        ['doc', 'Conto estintivo', 'Conteggio estintivo'],
                        ['text', 'Importo di estinzione (€) e data di validità'],
                    ]],
                    ['STEP_BENE_20', 'Rientro dell\'atto di benestare', 240, ['BO', 'BO', 'CdC', 'ATC'], [
                        ['text', 'Atto di benestare / messa in quota ricevuto (data). Oltre 10 giorni: sollecito formale via PEC o contatto con l\'ufficio paghe'],
                    ], [['level' => 1, 'delay_hours' => 240, 'action' => 'reassign', 'role' => 'COMPL']]],
                    ['STEP_BENE_30', 'Validità dei conteggi', 24, ['BO', 'COMPL', 'BANCA', null], [
                        ['text', 'Il conteggio estintivo è valido alla data di erogazione prevista? (sì / no)'],
                    ]],
                ],
                'templates' => [],
            ],
            [
                'code' => 'PROC_LIQUIDAZIONE',
                'name' => 'Liquidazione e perfezionamento',
                'description' => 'Verifica finale, richiesta di liquidazione al partner, originali cartacei e chiusura della pratica.',
                'trigger' => ['event' => 'pratica.stato_cambiato', 'name' => 'Benestare rientrato, da liquidare', 'stato' => 'PROC_LIQUIDAZIONE'],
                'steps' => [
                    ['STEP_LIQI_10', 'Verifica di compliance prima della liquidazione', 12, ['BO', 'COMPL', null, null], [
                        ['text', 'Controllo finale di date, coordinate bancarie e documenti'],
                    ]],
                    ['STEP_LIQI_20', 'Richiesta di liquidazione', 12, ['BO', 'BO', null, 'BANCA'], [
                        ['text', 'Richiesta inviata al partner (data e riferimento)'],
                    ]],
                    ['STEP_LIQI_30', 'Erogazione ed estinzioni', 48, ['BANCA', 'BANCA', 'BO', 'CdC'], [
                        ['text', 'Importo erogato (€) e data di liquidazione. Blocco della banca (IBAN o intestatario discordante)? Indicare il motivo: va alla compliance'],
                    ], [['level' => 2, 'delay_hours' => 48, 'action' => 'reassign', 'role' => 'COMPL']]],
                    ['STEP_LIQI_40', 'Ritiro degli originali cartacei', 120, ['CdC', 'BO', null, null], [
                        ['mail', 'Conferma di liquidazione al cliente', 'LIQI_CONFERMA', ['to_email' => '{external_data.cliente_email}']],
                        ['text', 'Originali del fascicolo ricevuti dal CdC (data). Finché mancano, le provvigioni dell\'agente restano bloccate'],
                    ]],
                    ['STEP_LIQI_50', 'Spedizione al partner e archiviazione', 48, ['BO', 'COMPL', 'BANCA', null], [
                        ['text', 'Plico cartaceo spedito al partner (data e tracking)'],
                    ]],
                ],
                'templates' => [
                    'LIQI_CONFERMA' => ['Liquidazione – conferma al cliente', 'Conferma avvenuta liquidazione finanziamento - Pratica {external_ref}',
                        'Gentile {external_data.cliente_nome}, siamo lieti di informarla che il suo finanziamento di {external_data.importo_erogato}€ è stato liquidato con successo.'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedProcess(array $definition): void
    {
        $process = Process::updateOrCreate(['code' => $definition['code']], [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'is_active' => true,
        ]);

        $templates = [];
        foreach ($definition['templates'] as $code => [$name, $subject, $body]) {
            $templates[$code] = EmailTemplate::updateOrCreate(['code' => $code, 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
                'name' => $name, 'type' => 'processo', 'subject' => $subject, 'body' => $body, 'is_active' => true,
            ]);
        }

        foreach ($definition['steps'] as $index => $step) {
            [$code, $name, $slaHours, $raci, $items] = $step;
            $task = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => ($index + 1) * 10], [
                'name' => $name,
                'code' => strtolower(str_replace('_', '-', $code)),
                'escalation_rules' => $this->escalationRules($slaHours, $raci[0] === $raci[1], $step[5] ?? null),
            ]);

            $this->seedRaci($task, $raci);

            foreach ($items as $position => $item) {
                $this->seedItem($task, ($position + 1) * 10, $item, $templates);
            }
        }

        $this->seedTrigger($process, $definition['trigger']);
    }

    /**
     * Livello 1: sollecito a chi ha in carico alla scadenza; livello 2: al responsabile al doppio. Le regole indicate nel passo
     * (escalation verso un altro ruolo) sostituiscono il livello corrispondente. Se R e A sono lo stesso ruolo (una sola riga per
     * ruolo nella RACI) non c'è un responsabile distinto: anche il livello 2 va a chi ha in carico.
     *
     * @param  array<int, array<string, mixed>>|null  $overrides
     * @return array<int, array<string, mixed>>
     */
    private function escalationRules(int $slaHours, bool $sameRoleRA, ?array $overrides): array
    {
        $rules = [
            1 => ['level' => 1, 'delay_hours' => $slaHours, 'action' => 'notify_assignee'],
            2 => ['level' => 2, 'delay_hours' => $slaHours * 2, 'action' => $sameRoleRA ? 'notify_assignee' : 'notify_manager'],
        ];

        foreach ($overrides ?? [] as $override) {
            $role = $override['role'];
            unset($override['role']);
            $rules[$override['level']] = $override + ['target_business_function' => $this->roles[$role]->id];
        }

        ksort($rules);

        return array_values($rules);
    }

    /**
     * Un ruolo ha una sola riga per passo (vincolo unico): se compare con più lettere vale la prima nell'ordine R, A, C, I.
     *
     * @param  array<int, string|null>  $raci  R, A, C, I
     */
    private function seedRaci(ProcessTask $task, array $raci): void
    {
        $kept = [];
        foreach (['R', 'A', 'C', 'I'] as $position => $letter) {
            if ($raci[$position] === null || in_array($raci[$position], array_slice($raci, 0, $position), true)) {
                continue;
            }
            $role = $this->roles[$raci[$position]];
            $task->raciAssignments()->updateOrCreate(['employee_type_id' => $role->id], ['raci_role' => $letter]);
            $kept[] = [$role->id, $letter];
        }

        // Quelle non più previste si tolgono (idempotenza se la specifica cambia).
        $task->raciAssignments()->get()
            ->reject(fn ($row) => in_array([$row->employee_type_id, $row->raci_role], $kept, true))
            ->each->delete();
    }

    /**
     * @param  array<int, mixed>  $item  ['text'|'doc'|'mail', ...]
     * @param  array<string, EmailTemplate>  $templates
     */
    private function seedItem(ProcessTask $task, int $order, array $item, array $templates): void
    {
        $attributes = ['action_type' => 'text_input', 'is_required' => true, 'config' => null, 'document_type_id' => null];

        switch ($item[0]) {
            case 'doc':
                $documentType = DocumentType::where('name', $item[1])->first();
                // Tipo documento assente nel core: si chiede comunque l'estremo del documento, senza crearlo qui (schema condiviso).
                $attributes = $documentType
                    ? ['action_type' => 'document_upload', 'document_type_id' => $documentType->id] + $attributes
                    : $attributes;
                $name = $item[2];
                break;
            case 'mail':
                $name = $item[1];
                $config = ['email_template_id' => $templates[$item[2]]->id] + $item[3];
                $attributes = ['action_type' => 'automated_email', 'is_required' => false, 'config' => $config] + $attributes;
                break;
            default:
                $name = $item[1];
                $attributes['is_required'] = $item[2] ?? true;
        }

        $task->processTaskItems()->updateOrCreate(['process_task_id' => $task->id, 'ordine' => $order], ['name' => $name] + $attributes);
    }

    /**
     * @param  array<string, mixed>  $trigger
     */
    private function seedTrigger(Process $process, array $trigger): void
    {
        $conditions = isset($trigger['stato'])
            ? [
                ['field' => 'tipo_finanziamento', 'operator' => '=', 'value' => 'Cessione'],
                ['field' => 'stato', 'operator' => '=', 'value' => self::STATI[$trigger['stato']]],
            ]
            : $trigger['conditions'];

        ProcessTrigger::updateOrCreate(['event_type' => 'external', 'external_event' => $trigger['event'], 'process_id' => $process->id], [
            'name' => $trigger['name'],
            'model_class' => 'external',
            'conditions' => $conditions,
            'is_active' => true,
        ]);
    }
}
