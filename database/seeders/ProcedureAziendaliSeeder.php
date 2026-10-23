<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use App\Models\Process;
use Illuminate\Database\Seeder;

/**
 * Procedure aziendali di un mediatore creditizio, ricavate dalla «Relazione sui requisiti organizzativi» (D.M. 31/2014) e dalla
 * struttura della rete distributiva. Si aggiungono a quelle già progettate (PRC-AML, PRC-TRANSP, PRC-OAM, PRC-ISTRUTTORIA,
 * PRC-COMPLIANCE, PRC-COGE, PRC-WHISTLE). Ogni processo ha i suoi passi in ordine e la matrice RACI per funzione aziendale
 * (R responsabile, A approva, C consultato, I informato). Le funzioni sono quelle dell'organigramma; i membri si assegnano da pannello.
 * Nei processi a scadenza (cron) la Segreteria di Rete è la responsabile di ogni passo: segue le scadenze e le sollecita. Idempotente.
 */
class ProcedureAziendaliSeeder extends Seeder
{
    /**
     * Funzioni dell'organigramma, nel database condiviso (unico-core): codice => [nome, macro area, tipo, esternalizzabile].
     * Quelle con codice già usato da altri seeder si riusano (COMPLIANCE, LEGAL, IT, BUS-BO, CTRL-AML).
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const FUNZIONI = [
        'CDA' => ['Consiglio di Amministrazione', 'Governance', 'Strategica', 'no'],
        'AD' => ['Amministratore Delegato', 'Governance', 'Strategica', 'no'],
        'AFFARI' => ['Affari societari e contabilità', 'Supporto', 'Supporto', 'yes'],
        'ORG-RU' => ['Organizzazione e Risorse Umane', 'Supporto', 'Supporto', 'no'],
        'COMMERCIALE' => ['Area Commerciale', 'Business / Commerciale', 'Operativa', 'no'],
        'SEGR-RETE' => ['Segreteria di Rete (segreteria amministrativa e di direzione)', 'Supporto', 'Operativa', 'no'],
        'INT-AUDIT' => ['Internal Audit', 'Controlli (III Livello)', 'Controllo', 'yes'],
        'DPO' => ['Data Protection Officer', 'Controlli / Privacy', 'Controllo', 'yes'],
        'COMPLIANCE' => ['Funzione Compliance', 'Controlli (II Livello)', 'Controllo', 'yes'],
        'CTRL-AML' => ['Funzione Antiriciclaggio', 'Controlli (II Livello)', 'Controllo', 'no'],
        'LEGAL' => ['Funzione Legale', 'Supporto', 'Supporto', 'yes'],
        'IT' => ['Information Technology e comunicazione digitale', 'Supporto', 'Supporto', 'partial'],
        'BUS-BO' => ['Back Office Prodotti', 'Business / Commerciale', 'Operativa', 'no'],
    ];

    /** Funzione che segue le scadenze: nei processi periodici è la responsabile (R) di ogni passo. */
    private const FUNZIONE_SCADENZE = 'SEGR-RETE';

    /**
     * codice => [nome, descrizione, cron (null = non periodico), passi]. Ogni passo: [nome, RACI per funzione].
     *
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: list<array{0: string, 1: array<string, string>}>}>
     */
    private function procedure(): array
    {
        return [
            // --- Rete distributiva ---
            'PRC-ONBOARD-COLL' => ['Selezione e inserimento di un collaboratore', 'Candidatura, istruttoria pre-contrattuale, contratto con firma digitale e adempimenti successivi (art. 5 D.M. 31/2014).', null, [
                ['Presentazione della candidatura (Scheda Candidato e curriculum)', ['COMMERCIALE' => 'R', 'AD' => 'A', 'SEGR-RETE' => 'I']],
                ['Istruttoria pre-contrattuale: protesti e pregiudizievoli, estratto CCIAA, requisiti di professionalità e onorabilità, prova valutativa', ['COMMERCIALE' => 'R', 'LEGAL' => 'C', 'AD' => 'A']],
                ['Predisposizione del contratto di agenzia (solo con documentazione completa e prova superata)', ['COMMERCIALE' => 'R', 'SEGR-RETE' => 'C', 'AD' => 'A']],
                ['Firma elettronica del contratto', ['COMMERCIALE' => 'R', 'SEGR-RETE' => 'I', 'AD' => 'A']],
                ['Adempimenti: iscrizione elenco OAM, posizione Enasarco (agenti), censimento presso le banche partner, account mail e portale, biglietti da visita', ['SEGR-RETE' => 'R', 'IT' => 'C', 'AD' => 'A', 'COMMERCIALE' => 'I']],
                ['Dichiarazione sostitutiva della PEC personale del collaboratore', ['SEGR-RETE' => 'R', 'COMPLIANCE' => 'I', 'AD' => 'A']],
            ]],
            'PRC-CESSAZIONE-COLL' => ['Cessazione o sospensione di un collaboratore', 'Chiusura del rapporto: comunicazione, cancellazione dall\'elenco OAM, revoca degli accessi e delle abilitazioni bancarie, chiusura della posizione provvigionale.', null, [
                ['Decisione di cessazione o sospensione e comunicazione al collaboratore', ['AD' => 'R', 'CDA' => 'A', 'COMMERCIALE' => 'C', 'COMPLIANCE' => 'I']],
                ['Cancellazione dall\'elenco OAM e chiusura della posizione Enasarco', ['SEGR-RETE' => 'R', 'AD' => 'A']],
                ['Revoca di account, portale e abilitazioni presso le banche partner', ['IT' => 'R', 'SEGR-RETE' => 'C', 'AD' => 'A']],
                ['Pratiche in corso: riassegnazione e chiusura della posizione provvigionale', ['COMMERCIALE' => 'R', 'AFFARI' => 'C', 'AD' => 'A']],
            ]],
            'PRC-ONORABILITA' => ['Verifica periodica dei requisiti di onorabilità', 'Controllo semestrale dei requisiti di onorabilità e professionalità di dipendenti e collaboratori: certificato del casellario e dei carichi pendenti (validità non anteriore a 45 giorni).', '0 8 1 1,7 *', [
                ['Richiesta dei certificati (casellario giudiziale e carichi pendenti) a tutti i collaboratori', ['SEGR-RETE' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Verifica dei certificati ricevuti e dei requisiti', ['COMPLIANCE' => 'R', 'SEGR-RETE' => 'C', 'CDA' => 'A']],
                ['Segnalazione al CdA delle non conformità e provvedimenti', ['COMPLIANCE' => 'R', 'CDA' => 'A', 'AD' => 'I']],
            ]],
            'PRC-FORMAZIONE-OAM' => ['Formazione e aggiornamento professionale OAM', 'Almeno 60 ore nel biennio, 15 ore per anno solare di cui 10 in presenza (Circolare OAM 19/14): pianificazione, monitoraggio delle ore e report.', '0 8 1 9 *', [
                ['Pianificazione dei corsi di formazione e aggiornamento per la rete', ['SEGR-RETE' => 'R', 'COMMERCIALE' => 'C', 'AD' => 'A']],
                ['Monitoraggio delle ore svolte da ciascun collaboratore', ['SEGR-RETE' => 'R', 'COMPLIANCE' => 'C', 'AD' => 'A']],
                ['Sollecito e provvedimenti per chi non ha raggiunto le ore', ['SEGR-RETE' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Report di fine anno a Compliance e Internal Audit', ['SEGR-RETE' => 'R', 'INT-AUDIT' => 'I', 'COMPLIANCE' => 'I']],
            ]],
            'PRC-PEC-COLLABORATORI' => ['Verifica e monitoraggio della PEC dei collaboratori', 'Obbligo di PEC personale attiva (art. 128-novies TUB): dichiarazione, messaggio di verifica entro 10 giorni lavorativi, regolarizzazione e verifica annuale entro il 30 novembre.', '0 8 2 11 *', [
                ['Acquisizione della dichiarazione sostitutiva e inserimento nel fascicolo del collaboratore', ['SEGR-RETE' => 'R', 'CDA' => 'A']],
                ['Invio del messaggio di verifica alla PEC dichiarata (entro 10 giorni lavorativi) e conservazione delle ricevute', ['SEGR-RETE' => 'R', 'IT' => 'C', 'CDA' => 'A']],
                ['Richiesta di regolarizzazione in caso di errore o mancata consegna (termine 10 giorni lavorativi)', ['SEGR-RETE' => 'R', 'CDA' => 'A']],
                ['Messaggio di verifica periodica annuale a tutte le PEC censite (entro il 30 novembre)', ['SEGR-RETE' => 'R', 'CDA' => 'A']],
                ['Mancato recapito: informativa al Presidente del CdA per sospensione o risoluzione del rapporto', ['SEGR-RETE' => 'R', 'CDA' => 'A', 'COMPLIANCE' => 'I']],
            ]],
            'PRC-VERIFICHE-RETE' => ['Controlli sulla rete dei collaboratori', 'Controlli telefonici e customer satisfaction, verifiche documentali con scheda di controllo, verifiche ispettive su almeno 1/5 della rete, sistema disciplinare.', null, [
                ['Controlli telefonici di verifica e customer satisfaction su un campione di clienti', ['LEGAL' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Verifica documentale con scheda di controllo (contratto di mediazione aggiornato, fogli informativi, materiale pubblicitario, GDPR)', ['LEGAL' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Verifiche ispettive su almeno 1/5 della rete distributiva', ['INT-AUDIT' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Registrazione degli esiti nella matrice e report conclusivo', ['LEGAL' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Sistema disciplinare graduato per le non conformità', ['AD' => 'R', 'CDA' => 'A', 'COMMERCIALE' => 'I']],
            ]],
            'PRC-LIVELLI-RETE' => ['Livelli, listini e produzione della rete', 'Attribuzione del livello per prodotto (Consulente del credito, Special, Premium, Supervisore), assegnazione dei listini provvigionali e verifica annuale della produzione per cluster.', '0 8 15 1 *', [
                ['Verifica della produzione dell\'anno per collaboratore e per cluster di prodotto', ['COMMERCIALE' => 'R', 'AFFARI' => 'C', 'AD' => 'A']],
                ['Attribuzione o revisione del livello per prodotto', ['COMMERCIALE' => 'R', 'AD' => 'A', 'SEGR-RETE' => 'I']],
                ['Assegnazione dei listini provvigionali e comunicazione al collaboratore', ['SEGR-RETE' => 'R', 'COMMERCIALE' => 'C', 'AD' => 'A']],
            ]],
            'PRC-COORDINAMENTO' => ['Coordinamento e Supervisione della rete', 'Inserimento di collaboratori nella struttura di un Consulente Premium con coordinamento, ruolo del Supervisore e over provvigionali.', null, [
                ['Richiesta di coordinamento da parte di un Consulente Premium', ['COMMERCIALE' => 'R', 'AD' => 'A']],
                ['Verifica dei requisiti del coordinatore e del Supervisore', ['COMMERCIALE' => 'R', 'COMPLIANCE' => 'C', 'AD' => 'A']],
                ['Associazione dei collaboratori coordinati e dei listini «come coordinatore»', ['SEGR-RETE' => 'R', 'AFFARI' => 'C', 'AD' => 'A']],
            ]],

            // --- Pratiche ---
            'PRC-PERFEZIONAMENTO' => ['Perfezionamento della pratica', 'Raccolta dei documenti per la fattibilità, informativa privacy con firma olografa, KYC, plico da firmare con OTP e approvazione, fino all\'invio in istruttoria.', null, [
                ['Raccolta dei documenti del cliente secondo la check list del prodotto', ['COMMERCIALE' => 'R', 'BUS-BO' => 'C', 'AD' => 'A']],
                ['Informativa privacy firmata e lettura dei documenti d\'identità', ['COMMERCIALE' => 'R', 'DPO' => 'I', 'BUS-BO' => 'C']],
                ['Anagrafica e adeguata verifica (KYC) compilate dall\'agente', ['COMMERCIALE' => 'R', 'CTRL-AML' => 'C', 'BUS-BO' => 'A']],
                ['Stampa e firma OTP della modulistica; approvazione dei documenti che la richiedono', ['BUS-BO' => 'R', 'COMPLIANCE' => 'C', 'AD' => 'A']],
                ['Controllo di primo livello del fascicolo (completezza, trasparenza, antiriciclaggio)', ['BUS-BO' => 'R', 'COMPLIANCE' => 'I', 'AD' => 'A']],
            ]],
            'PRC-INTEGRAZIONI-BANCA' => ['Integrazioni richieste dalla banca', 'Documentazione integrativa chiesta dalla banca dopo l\'inserimento della pratica: richiesta all\'agente, raccolta, controllo e invio.', null, [
                ['Registrazione della richiesta della banca e dei documenti integrativi', ['BUS-BO' => 'R', 'COMMERCIALE' => 'I', 'AD' => 'A']],
                ['Richiesta all\'agente e al cliente, con solleciti alla scadenza', ['BUS-BO' => 'R', 'COMMERCIALE' => 'C', 'AD' => 'A']],
                ['Controllo dei documenti ricevuti e invio alla banca', ['BUS-BO' => 'R', 'AD' => 'A']],
            ]],
            'PRC-ESITO-BANCA' => ['Inserimento in banca ed esito della pratica', 'Inserimento sul portale della banca, controllo degli esiti e aggiornamento dello stato (approvata, declinata, sospesa, erogata) con le date.', null, [
                ['Inserimento della pratica sul portale della banca e registrazione del codice assegnato', ['BUS-BO' => 'R', 'AD' => 'A']],
                ['Controllo periodico dell\'esito sul portale (pratiche inviate senza risposta)', ['BUS-BO' => 'R', 'AD' => 'A']],
                ['Aggiornamento dello stato con motivazione e comunicazione all\'agente', ['BUS-BO' => 'R', 'COMMERCIALE' => 'I', 'AD' => 'A']],
                ['Erogazione: verifica della contabile e avvio della maturazione provvigionale', ['BUS-BO' => 'R', 'AFFARI' => 'C', 'AD' => 'A']],
            ]],
            'PRC-PROVVIGIONI' => ['Maturazione e pagamento delle provvigioni', 'Provvigioni sulle pratiche erogate: verifica con la banca, applicazione dei listini, fatturazione, pagamento e storni.', null, [
                ['Verifica delle provvigioni maturate con gli estratti della banca', ['AFFARI' => 'R', 'BUS-BO' => 'C', 'AD' => 'A']],
                ['Applicazione dei listini e delle over provvigionali della rete', ['AFFARI' => 'R', 'COMMERCIALE' => 'C', 'AD' => 'A']],
                ['Fatturazione e pagamento ai collaboratori', ['AFFARI' => 'R', 'AD' => 'A']],
                ['Storni e recuperi (pratiche estinte o stornate dalla banca)', ['AFFARI' => 'R', 'COMMERCIALE' => 'C', 'AD' => 'A']],
            ]],
            'PRC-ARCHIVIAZIONE' => ['Archiviazione dei fascicoli', 'Conservazione dei fascicoli delle pratiche e dei collaboratori secondo la Comunicazione OAM n. 15/2017 e la durata di legge.', null, [
                ['Archiviazione del fascicolo a chiusura della pratica', ['BUS-BO' => 'R', 'AD' => 'A']],
                ['Controllo della conservazione e dei backup dei documenti', ['IT' => 'R', 'DPO' => 'C', 'AD' => 'A']],
                ['Scarto o cancellazione alla scadenza del termine di conservazione', ['BUS-BO' => 'R', 'DPO' => 'C', 'AD' => 'A']],
            ]],

            // --- Clienti e trasparenza ---
            'PRC-PUBBLICITA' => ['Messaggi pubblicitari', 'Verifica preventiva dei messaggi pubblicitari: natura promozionale, rinvio ai fogli informativi, nominativo dell\'istituto erogante, costi di mediazione.', null, [
                ['Predisposizione del messaggio da parte dell\'area commerciale', ['COMMERCIALE' => 'R', 'COMPLIANCE' => 'C']],
                ['Verifica di conformità ai requisiti di trasparenza', ['COMPLIANCE' => 'R', 'AD' => 'A']],
                ['Autorizzazione e diffusione alla rete', ['COMMERCIALE' => 'R', 'AD' => 'A', 'SEGR-RETE' => 'I']],
            ]],
            'PRC-RECLAMI' => ['Gestione dei reclami', 'Ricezione (lettera, email, PEC), registrazione, istruttoria e risposta al cliente entro 60 giorni; analisi dei dati per il miglioramento.', null, [
                ['Ricezione e registrazione del reclamo', ['COMPLIANCE' => 'R', 'SEGR-RETE' => 'C', 'AD' => 'I']],
                ['Istruttoria: acquisizione della documentazione e della relazione della persona o funzione interessata', ['COMPLIANCE' => 'R', 'COMMERCIALE' => 'C']],
                ['Risposta formale al cliente entro 60 giorni dalla ricezione', ['COMPLIANCE' => 'R', 'AD' => 'A']],
                ['Eventuale mediazione (art. 5 D.Lgs. 28/2010) e chiusura del reclamo', ['COMPLIANCE' => 'R', 'LEGAL' => 'C', 'AD' => 'A']],
                ['Analisi periodica dei reclami e azioni di miglioramento', ['COMPLIANCE' => 'R', 'CDA' => 'A']],
            ]],
            'PRC-SOS' => ['Segnalazione di operazione sospetta', 'Valutazione degli indicatori di anomalia, decisione del Responsabile SOS, eventuale segnalazione alla UIF e riservatezza della segnalazione.', null, [
                ['Rilevazione dell\'anomalia da parte del collaboratore o del back office', ['BUS-BO' => 'R', 'CTRL-AML' => 'I']],
                ['Valutazione secondo gli indicatori di anomalia del Regolamento Antiriciclaggio', ['CTRL-AML' => 'R', 'COMPLIANCE' => 'C', 'AD' => 'A']],
                ['Decisione del Responsabile SOS e segnalazione alla UIF', ['CTRL-AML' => 'R', 'AD' => 'A']],
                ['Archiviazione riservata e tracciamento della decisione', ['CTRL-AML' => 'R', 'AD' => 'A']],
            ]],
            'PRC-REVISIONE-KYC' => ['Revisione periodica dell\'adeguata verifica', 'Aggiornamento dei dati e del livello di rischio dei clienti, verifiche PEP e sanzioni, in base al profilo di rischio.', '0 8 1 6 *', [
                ['Individuazione dei clienti da rivedere in base al livello di rischio', ['CTRL-AML' => 'R', 'BUS-BO' => 'C', 'AD' => 'A']],
                ['Aggiornamento dei dati e verifica PEP e liste sanzioni', ['BUS-BO' => 'R', 'CTRL-AML' => 'C', 'AD' => 'A']],
                ['Valutazione del rischio e relazione al responsabile antiriciclaggio', ['CTRL-AML' => 'R', 'AD' => 'A']],
            ]],

            // --- Governance e controlli ---
            'PRC-RELAZIONE-ORGANIZZATIVA' => ['Aggiornamento della Relazione sui requisiti organizzativi', 'Almeno due volte l\'anno e a ogni modifica organizzativa rilevante (art. 6 D.M. 31/2014): composizione del CdA, funzioni esternalizzate, responsabili di area, approvazione e conservazione per la vigilanza.', '0 8 1 1,7 *', [
                ['Verifica dei dati: organi sociali, responsabili di area, funzioni esternalizzate e referenti', ['AD' => 'R', 'ORG-RU' => 'C', 'CDA' => 'A']],
                ['Aggiornamento del testo e della data di approvazione', ['AD' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Approvazione del CdA e conservazione a disposizione della vigilanza', ['CDA' => 'R', 'AD' => 'A', 'AFFARI' => 'I']],
            ]],
            'PRC-ESTERNALIZZAZIONE' => ['Funzioni esternalizzate e controllo degli outsourcer', 'Selezione dell\'outsourcer, delibera del CdA, contratto con clausole risolutive e verifica almeno annuale (internal audit, compliance, DPO, contabilità).', '0 8 1 2 *', [
                ['Valutazione dell\'outsourcer: solidità, conflitti di interesse, qualità del servizio', ['AD' => 'R', 'LEGAL' => 'C', 'CDA' => 'A']],
                ['Delibera di conferimento dell\'incarico e sottoscrizione del contratto', ['CDA' => 'R', 'AD' => 'A', 'LEGAL' => 'C']],
                ['Verifica almeno annuale delle prestazioni rispetto a standard e tempi del contratto', ['AD' => 'R', 'CDA' => 'A']],
                ['Report e incontri di verifica con l\'outsourcer, rinnovo o revoca', ['AD' => 'R', 'CDA' => 'A']],
            ]],
            'PRC-MAPPA-RISCHI' => ['Mappa dei rischi e controlli di secondo livello', 'Identificazione e monitoraggio dei rischi tipici (riciclaggio, legale, reputazionale, trasparenza, non conformità, rete vendita, delitti informatici, requisiti, dati), presìdi e reportistica agli organi.', '0 8 1 3,9 *', [
                ['Aggiornamento della mappa dei rischi e dei presìdi per ciascun rischio', ['COMPLIANCE' => 'R', 'AD' => 'C', 'CDA' => 'A']],
                ['Monitoraggio del rispetto delle norme e adeguatezza delle procedure interne', ['COMPLIANCE' => 'R', 'BUS-BO' => 'C', 'CDA' => 'A']],
                ['Reportistica periodica agli organi aziendali', ['COMPLIANCE' => 'R', 'CDA' => 'A', 'AD' => 'I']],
            ]],
            'PRC-AUDIT-INTERNO' => ['Audit interno (terzo livello)', 'Programma annuale delle verifiche autorizzato dal CdA, verifiche sull\'applicazione delle procedure, raccomandazioni per il miglioramento dei controlli.', '0 8 1 2 *', [
                ['Programma annuale delle verifiche e autorizzazione del CdA', ['INT-AUDIT' => 'R', 'CDA' => 'A']],
                ['Verifiche su procedure, indipendenza e convenzioni bancarie', ['INT-AUDIT' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
                ['Relazione al CdA e raccomandazioni', ['INT-AUDIT' => 'R', 'CDA' => 'A', 'AD' => 'I']],
                ['Follow-up sull\'attuazione delle raccomandazioni', ['INT-AUDIT' => 'R', 'AD' => 'C', 'CDA' => 'A']],
            ]],
            'PRC-INDIPENDENZA-BANCHE' => ['Verifica dell\'indipendenza dai finanziatori', 'Verifica annuale dei volumi intermediati per ogni partner bancario e della distribuzione del portafoglio, per evitare la concentrazione su un unico finanziatore.', '0 8 15 1 *', [
                ['Calcolo dei volumi per banca e per prodotto', ['AFFARI' => 'R', 'BUS-BO' => 'C', 'AD' => 'A']],
                ['Analisi della distribuzione del portafoglio e delle convenzioni di mediazione', ['AD' => 'R', 'COMPLIANCE' => 'C', 'CDA' => 'A']],
            ]],
            'PRC-RC-PROFESSIONALE' => ['Polizza di responsabilità civile professionale', 'Mantenimento della copertura assicurativa obbligatoria con i massimali stabiliti dall\'OAM: scadenza, rinnovo e controllo.', '0 8 1 11 *', [
                ['Verifica della scadenza e dei massimali richiesti dall\'OAM', ['AFFARI' => 'R', 'AD' => 'A']],
                ['Rinnovo della polizza e archiviazione della quietanza', ['AFFARI' => 'R', 'AD' => 'A', 'SEGR-RETE' => 'I']],
            ]],
            'PRC-PRIVACY' => ['Protezione dei dati personali', 'Diritti degli interessati, violazioni dei dati (notifica entro 72 ore), registro dei trattamenti e nomine, con il DPO (GDPR).', null, [
                ['Richieste degli interessati: registrazione e riscontro entro i termini', ['DPO' => 'R', 'BUS-BO' => 'C', 'AD' => 'A']],
                ['Violazione dei dati: valutazione e notifica al Garante entro 72 ore se dovuta', ['DPO' => 'R', 'IT' => 'C', 'AD' => 'A']],
                ['Registro dei trattamenti e nomine dei responsabili', ['DPO' => 'R', 'AFFARI' => 'C', 'AD' => 'A']],
            ]],
            'PRC-SICUREZZA-IT' => ['Sicurezza informatica e continuità', 'Policy di sicurezza, backup, valutazione annuale del personale IT e gestione degli incidenti (rischio delitti informatici).', '0 8 1 4 *', [
                ['Verifica delle policy di sicurezza e dei backup', ['IT' => 'R', 'DPO' => 'C', 'AD' => 'A']],
                ['Valutazione annuale del personale della funzione IT', ['AD' => 'R', 'ORG-RU' => 'C', 'CDA' => 'A']],
                ['Gestione degli incidenti di sicurezza e ripristino', ['IT' => 'R', 'DPO' => 'C', 'AD' => 'A']],
            ]],
        ];
    }

    public function run(): void
    {
        $funzioni = [];

        foreach (self::FUNZIONI as $codice => [$nome, $macroArea, $tipo, $esternalizzabile]) {
            $funzioni[$codice] = EmployeeType::office($codice, [
                'name' => $nome, 'macro_area' => $macroArea, 'type' => $tipo, 'outsourcable_status' => $esternalizzabile,
            ]);
        }

        foreach ($this->procedure() as $codice => [$nome, $descrizione, $cron, $passi]) {
            $periodico = $cron !== null;

            $processo = Process::updateOrCreate(['code' => $codice], [
                'name' => $nome,
                'description' => $descrizione,
                // Quelli a scadenza sono seguiti dalla Segreteria di Rete (R su ogni passo).
                'is_active' => true,
                'is_periodic' => $periodico,
                'cron_expression' => $cron,
            ]);

            foreach ($passi as $indice => [$nomePasso, $raci]) {
                $passo = $processo->tasks()->updateOrCreate(
                    ['process_id' => $processo->id, 'ordine' => ($indice + 1) * 10],
                    ['name' => $nomePasso, 'code' => strtolower($codice).'-'.($indice + 1)],
                );

                if ($periodico) {
                    $raci = $this->conSegreteriaResponsabile($raci);
                }

                foreach ($raci as $funzione => $ruolo) {
                    $passo->raciAssignments()->updateOrCreate(['employee_type_id' => $funzioni[$funzione]->id], ['raci_role' => $ruolo]);
                }

                $passo->raciAssignments()->whereNotIn('employee_type_id', collect(array_keys($raci))->map(fn (string $f): int => $funzioni[$f]->id))->delete();
            }

            $processo->tasks()->where('ordine', '>', count($passi) * 10)->delete();
        }
    }

    /**
     * Nei processi a scadenza la Segreteria di Rete è responsabile (R); chi lo era prima resta consultato (C).
     *
     * @param  array<string, string>  $raci
     * @return array<string, string>
     */
    private function conSegreteriaResponsabile(array $raci): array
    {
        foreach ($raci as $funzione => $ruolo) {
            if ($ruolo === 'R') {
                $raci[$funzione] = 'C';
            }
        }

        $raci[self::FUNZIONE_SCADENZE] = 'R';

        return $raci;
    }
}
