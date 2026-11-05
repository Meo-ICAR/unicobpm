# Specifiche di dominio — UnicoBPM

> Documento di riferimento per chi (persona o agente AI) lavora su questo codebase.
> Nasce da una rianalisi approfondita del codice esistente: ogni convenzione riportata qui è stata
> **verificata contro lo schema reale del database**, non dedotta dai soli commenti nel codice (che in
> più punti si sono rivelati disallineati dallo schema effettivo — vedi §7). Tenerlo aggiornato quando
> cambia lo schema o si risolvono i problemi aperti.

## 1. Cos'è questa applicazione

UnicoBPM è un motore di **Business Process Management (BPM)** su Laravel 13 + Filament 5 (pacchetto `meo-icar/unico-core`), ad uso di
mediatori creditizi. Gestisce "pratiche" (istanze di processo) che avanzano attraverso una sequenza di
task, con assegnazione automatica basata su una matrice RACI per funzione aziendale, checklist di
raccolta dati con regole di respingimento (KO), promemoria/escalation SLA, e integrazione con sistemi
anagrafici e documentali esterni.

L'applicazione è pilotata da Filament (redirect `/` verso `/admin`). Oltre al pannello espone poche API
(`POST /api/events`, `/api/bpm/available-activities`, `/api/verify-token`, `/api/api-logs` e il bridge
`/api/commands` di unico-core) e fa da coordinatore della suite Unico: chiama le altre app solo via API
(`ExternalAppClient`). Il dettaglio di comportamento, rotte, comandi e `.env` sta nei manuali
(`resources/manuals/`); questo file tiene le convenzioni e i punti aperti.
Ultima verifica sul codice: 2026-11-05.

## 2. Architettura multi-database

| Connessione | Variabili | Cosa ci vive |
|---|---|---|
| `mysql` (default) | `DB_*` | Il motore BPM: `Process`, `ProcessTask`, `ProcessTaskItem`, `ProcessTaskRaci`, `ProcessInstance`, `ProcessTaskExecution`, `ProcessTaskItemAnswer`, `ProcessTrigger`, `Checklist*`, `ProcessEmailReply`, `ProcessPostponement`, `ExternalEvent`, `OutboundCall`, `WebhookEndpoint`, `ApiLog`, `AiActionDraft`, `ChatMessage`, `User`, più `activity_log`. Le migration di `database/migrations/` gestiscono solo queste. |
| `core` | `CORE_DB_*` (altrimenti `DB_*`) | Schema condiviso di unico-core: `Company`, `Employee`, `Client`, `EmployeeType` (ruoli/uffici), `Document`, `DocumentType`, `EmailTemplate`, `Resource`, permessi. I model locali in `app/Models` estendono i model del pacchetto (`Unico\Core\Models\*`). |
| `mysql_unicooam`, `proforma` | storiche | Ancora definite in `config/database.php`, ma i model condivisi ora leggono dal core (in sviluppo è lo stesso database `unicosuite`). **Non dichiarare `$connection` su queste.** |

Regole:
- Questa app **non esegue le migration del core** (`UNICO_CORE_MIGRATE=false`) e i suoi seeder non vanno
  lanciati sul database condiviso. Lo schema condiviso si cambia solo in unico-core (`unico-core:migrate`,
  migration protette da `hasTable`/`hasColumn`).
- Tra connessioni diverse niente `->constrained()`: colonna intera con indice, senza vincolo
  (es. `process_task_items.document_type_id`, `process_task_item_answers.document_id`). Id sempre interi.
- Prima di usare `Document`, `DocumentType`, `EmailTemplate`, `Client`, `Employee`: leggere il model (e quello
  del pacchetto in `vendor/meo-icar/unico-core`) per i nomi reali. Esempio storico: `documentable_type`/
  `documentable_id`/`document_url`, non `subject_*`/`file_path`.
- Le altre app si leggono via API, non per tabella (vedi CLAUDE.md).

## 3. Modello di dominio

```
Process (template)
 ├─ ProcessTask (step ordinati per "ordine"; employee_type_id = ruolo responsabile)
 │   ├─ ProcessTaskItem (azioni: document_upload, fill_checklist, text_input, automated_email,
 │   │                   validation_rule, system_task, external_check, external_command, await_email_reply)
 │   └─ ProcessTaskRaci (R/A/C/I per employee_type_id)
 └─ ProcessInstance (la pratica)
     ├─ subject polimorfo NULLABLE; per le istanze senza soggetto: title, external_ref, external_data
     ├─ currentTask → ProcessTask
     ├─ ProcessTaskExecution (una riga per step attraversato; postponed_until, claimed_at, mandatory_days_to_complete)
     ├─ ProcessTaskItemAnswer
     ├─ ProcessEmailReply / ProcessPostponement (risposte email e rinvii)
     └─ logs() → activity_log

EmployeeType = ruolo / ufficio (ex BusinessFunction): codice, area, email ufficio, outsourcable_status,
manager. Chi lo ricopre sta in `employee_roles` di employees/clients. Corrispondenza vecchie funzioni in
`EmployeeType::OFFICE_ROLES`; nei seeder `EmployeeType::office($codice, [...])`.

Checklist → ChecklistItem (regole KO) → ChecklistAnswer (per istanza)
ProcessTrigger → avvia un Process su evento di un model (created/updated/idle) o su evento esterno
(`event_type=external`, da `POST /api/events`)
```

La tabella `business_functions` esiste solo per storico: **il model `BusinessFunction` non c'è più**.

### Convenzioni di naming da NON violare
- Ordinamento dei task: **`ordine`** (mai `order`).
- Ruolo RACI: colonna **`raci_role`**, valori **`R`/`A`/`C`/`I`**; tabella **`process_task_raci`**; il ruolo si
  indica con **`employee_type_id`** (non più `business_function_id`).
- Stato istanza (`process_instances.status`): **`pending`, `in_progress`, `completed`, `rejected`,
  `cancelled`, `suspended`** (mai `running`).
- Stato esecuzione (`process_task_executions.execution_status`): **`pending`, `in_progress`, `completed`,
  `rejected_and_rewinded`** (colonna `execution_status`, non `status`).
- Ricorrenza: **`is_periodic`** + `recurrence_frequency`/`recurrence_day` (calendario); **`cron_expression`** per
  la valutazione periodica di condizioni. Filtri sul model target: **`trigger_filters`**.
- Scadenza tassativa di step: `mandatory_days_to_complete` (esecuzione) e `hard_deadline_at` (pratica).
  `ProcessTask.days_to_complete` non esiste.
- Gli stati delle pratiche delle altre app stanno in `pratica_stati`, mai nella vecchia `pratiches_statos`.

## 4. Motore di scheduling (`routes/console.php`)

- `bpm:run-scheduler` ogni giorno alle 06:00: trigger «idle» e processi ricorrenti (`is_periodic`).
- Ogni minuto: rilancia `ExecutePeriodicProcessJob` per i processi con `next_run_at` passato; inoltre un
  `Schedule::job(...)->cron(...)` per ogni processo con `cron_expression`.
- Ogni ora: `TaskEscalationWatchdogJob` (solleciti e escalation, via `EscalationNotifier`, salta gli step con
  rinvio valido) e `MandatoryDeadlineWatchdogJob`.
- `bpm:send-reminders` (lunedì 08:30), `bpm:check-stale-records` (08:00).
- `bpm:dispatch-external-command unicocoge vcoge:calculate --option=invia` il 5 di ogni mese alle 06:00;
  `venasarco-trimestre:calculate` il 5 di gennaio/aprile/luglio/ottobre.
- `unico-core:mail-fetch --type=processo` ogni 5 minuti: le risposte alle email dei processi.
- Le scritture verso le altre app e i webhook passano da `OutboundCallService` → `SendOutboundCall`
  (tabella `outbound_calls`, ritentativi 1, 5, 15 min, 1 h, 4 h).

`DataAnomalyWatchdogJob` non esiste più (dipendeva da `Customer`, esterno). `bpm:read-emails` è sostituito da
`HandleProcessEmailReply` + `ProcessAttachmentIngestor`.

## 5. Audit log

`alizharb/filament-activity-log` (su `spatie/laravel-activitylog`), tabella `activity_log`. I model del
processo scrivono sulla connessione predefinita; se il database dell'app è diverso dal core la migration
`create_activity_log_if_missing` la crea anche qui. Niente model custom tipo `ProcessInstanceLog`.

```php
activity('bpm')->causedBy(auth()->user())->performedOn($processInstance)
    ->event('nome_evento')->withProperties([...])->log('descrizione leggibile');
```

Le chiamate in uscita finiscono anche in `api_logs` (`ApiLogger`), con `X-Correlation-Id` (`Correlation`).
Niente dati personali nei log, nessuna chiave in codice o commit.

## 6. Confini di integrazione esterna

1. Le altre app (unicofinance, unicooam, unicocoge, unicopec, unicogdpr) si chiamano solo con
   `ExternalAppClient` / `ExternalAppResolver`; gli indirizzi sono in `config/services.php` (`apps`).
2. Un model che manca in `app/Models` va prima collocato: se è schema condiviso sta in unico-core, se è di
   un'altra app arriva via API. Nel dubbio chiedere. Un riferimento a una classe interna al motore (log,
   stato di workflow) è invece un gap locale.
3. Nessuna app esterna ha un indirizzo di ripiego di produzione (unicofinance, unicooam, unicocoge, unicopec,
   unicogdpr, daishboard): se la variabile `*_API_URL` manca, l'app risulta sconosciuta. Sul server di sviluppo
   gli indirizzi sono `https://<app>.unicocompilance.it`. `whistle` e `unicoaiact` hanno ancora un ripiego,
   che punta già a questo dominio.
4. Il passaggio SSO verso le app con `sso_post` va da `/passa-a/{app}` (token in POST); `switchTo` risponde 404.

## 7. Problemi noti

Verificati sul codice il 2026-11-05.

### Risolti dall'ultima versione
- Observer registrati, nomi colonna corretti, `subject_*` nullable, `title` su `ProcessInstance`,
  `cron_expression` aggiunta, `trigger_filters` corretto, audit sul plugin.
- Funzioni aziendali confluite in `employee_types`; `unicoloan` → `unicofinance`; `Proforma` → `UnicoCoge`.
- Sollecito: `SendProcessReminders` ora parte dalla creazione dello step se non c'è un sollecito precedente.
- Mancavano `ReminderDocumentsMail` e il caricamento pubblico: i documenti arrivano ora dalle risposte email.
- Default di produzione tolti per tutte le app esterne (unicooam, unicogdpr, daishboard compresi).
- Rinvii, risposte email (`EmailReplyAgent`), webhook, chiamate in uscita in coda, eventi esterni, whistleblowing.

### Aperti
- **Queue worker**: `QUEUE_CONNECTION=database` e `SendOutboundCall`, `SaveSentMailCopy`,
  `ExecutePeriodicProcessJob`, `TaskEscalationWatchdogJob`, `MandatoryDeadlineWatchdogJob` sono `ShouldQueue`.
  Su questa macchina non gira nessun `queue:work`: senza un worker persistente (Supervisor/systemd) i job
  restano in coda e le scritture verso le altre app non partono. Ora più rilevante di prima.
- **`yearly`** in `BpmSchedulerCommand` non fa partire nulla (`case` vuoto).
- **`HasBpmTriggers::evaluateBpmConditions()`**: `equals` debole su campi data/datetime (un `Carbon` non
  coincide mai con `'2024-01-01'`); con più condizioni su `updated` basta una non modificata per bloccare il
  trigger (andrebbe «almeno una dirty», poi «tutte corrispondono»).
- **`ExecutePeriodicProcessJob`** non valuta `trigger_field`/`trigger_state`/`trigger_value` per soggetto.
- **Log fuorviante**: gli errori di configurazione (app sconosciuta, `InvalidArgumentException`) e gli errori
  di rete usano lo stesso testo «fallita per errore di rete» in `ProcessTaskExecutionObserver`,
  `ExternalCheckRunner` e `CheckStaleExternalRecords`.
- **Policy Filament**: l'accesso alle risorse non è isolato per company a livello di query.

## 8. Linee guida operative per sessioni di coding assistito da AI

1. Non fidarsi dei commenti per nomi di colonna/enum: verificare con la migration o `database-schema`.
2. Se una query usa una relazione/colonna, aprire il model e controllare il nome esatto (`businessFunctions`
   non esiste più; il ruolo è `employeeType`).
3. Niente foreign key tra connessioni diverse.
4. Migration solo dell'app, mai quelle del core; provarle su un DB isolato e controllare `migrate:status`.
5. Dopo modifiche a `StartProcessAction`, `AdvanceProcessAction`, agli Observer o a `ExternalAppClient`,
   smoke test reale con dati di prova (poi ripuliti).
6. Se una classe non esiste in `app/Models`, prima capire a chi appartiene (§6).
7. Un comando/job non schedulato o non registrato è un segnale d'allarme: verificarne le dipendenze.
8. Dopo ogni modifica a spec o manuali: `php artisan manual:sync` (l'assistente li indicizza; la spec è tra le
   fonti) e, per la parte «Fatti dal codice», `python3 /var/www/html/unico-core/tools/manuals/gen_facts.py /var/www/html/unicobpm`.

## 9. Intelligenza artificiale

Tutti gli agenti (`app/Neuron`, NeuronAI) usano Anthropic con `ANTHROPIC_API_KEY` e `ANTHROPIC_MODEL`
(default nel codice `claude-sonnet-4-6`); gli embedding sono Gemini (`GEMINI_EMBEDDINGS_MODEL`,
`gemini-embedding-001`). Un solo modello per tutti gli agenti.

| Agente | Compito | max_tokens |
|---|---|---|
| `EmailReplyAgent` | interpreta il testo di una risposta email (esito, rinvio, confidenza) | 512 |
| `DocumentClassifierAgent` | classifica un documento allegato | 1024 |
| `ManualAssistantAgent` | assistente RAG sui manuali, SQL in sola lettura su tabelle in lista bianca | — |
| `SegretariaAiAgent` | assistente operativo: bozze di email (`AiActionDraft`), conversazioni passate | — |
| `DataNavigatorAgent` | esplora i dati con strumenti SQL in sola lettura | 8192 |

## 10. Testing

`tests/Feature` ha circa 50 test (API eventi, chiamate in uscita, risposte email, rinvii, watchdog, solleciti,
seeder dei processi, smoke delle risorse Filament, assistente AI). `tests/Unit` è vuota. Mancano ancora test
mirati su `StartProcessAction`, `AdvanceProcessAction` e sul rigetto KO delle checklist. Quando un test tocca
model del core, ricordare che usano il database configurato (non l'in-memory) anche con `DB_CONNECTION=sqlite`:
isolare i dati con codici dedicati e ripulirli.

## 11. Seeder

`database/seeders/` contiene dati di riferimento e processi: `BpmDesignSeeder`, `CreditBrokerProcessesSeeder`,
`WhistleblowingProcessSeeder`, `ComplianceDocumentazioneProcessSeeder`, `ScadenziarioCogeProcessSeeder`,
`RettificaIbanProcessSeeder`, `IbanAllineamentoProcessSeeder`, `ValidazioneFatturaProformaProcessSeeder`,
`ProcedureAziendaliSeeder`, `OrganigrammaSeeder`, `OfficeRoleSeeder`, `ProcessEmailTemplateSeeder` e altri.
Tutti idempotenti (`updateOrCreate`/`firstOrCreate` su chiave naturale, mai ID fissi). **Non lanciare
`db:seed` generico sul database condiviso**: solo seeder singoli e noti (`--class=`).
Attenzione alle collisioni: due seeder sullo stesso `(process_id, ordine)` si sovrascrivono; controllare gli
`ordine` occupati prima di aggiungere step a un processo già seminato.

## 12. Processi della Cessione del Quinto

Sei processi (`PROC_ORIGINATION`, `PROC_ISTRUTTORIA`, `PROC_DELIBERA`, `PROC_NOTIFICA`, `PROC_BENESTARE`, `PROC_LIQUIDAZIONE`) in
`CessioneQuintoProcessesSeeder`, dalla specifica `bpm_process_specification.md` (non nel repo). Scelte da conoscere:
- **Avvio**: eventi esterni `lead.creato` e `pratica.stato_cambiato` (condizioni `tipo_finanziamento = Cessione` e `stato`). Gli stati
  della specifica (PROPOSAL_SIGNED...) non esistono in `pratica_stati`: la corrispondenza è in `CessioneQuintoProcessesSeeder::STATI`
  (RICHIESTA ISTRUTTORIA, FASCICOLO COMPLETO, ATTO FISSATO, IN ATTESA BENESTARE, RIENTRO BENESTARE). UnicoFinance non emette ancora gli eventi.
- **Ruoli**: CdC = `BUS-RETE-EXT`, BO/Delegato = `BUS-BO`, Resp. BO/Compliance = `COMPLIANCE`; creati i ruoli esterni `ATC-ENTE` e `PARTNER-BANCA`.
  Un ruolo ha una sola riga per passo (vincolo unico): se R e A coincidono resta la riga R e il livello 2 di escalation va a chi ha in carico.
- **Scadenze**: l'SLA di ogni passo è il livello 1 di `escalation_rules` (a chi ha in carico), il livello 2 è al doppio (al responsabile); le
  escalation alla compliance della specifica sono azioni `reassign`.
- **Non realizzato**: scrittura di `practice.*` e dei nuovi stati (SUSPENDED_*, CLOSED_LOST...), perché le istanze nate da evento non hanno un
  soggetto locale; le email condizionali (richiesta integrazione, sollecito all'ATC) perché le azioni email partono all'ingresso nel passo.
  Gli esiti anomali si registrano in campi di testo del passo. I tipi documento mancanti nel core (SECCI) diventano campi di testo.
