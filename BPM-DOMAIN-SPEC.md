# Specifiche di dominio — UnicoBPM

> Documento di riferimento per chi (persona o agente AI) lavora su questo codebase.
> Nasce da una rianalisi approfondita del codice esistente: ogni convenzione riportata qui è stata
> **verificata contro lo schema reale del database**, non dedotta dai soli commenti nel codice (che in
> più punti si sono rivelati disallineati dallo schema effettivo — vedi §7). Tenerlo aggiornato quando
> cambia lo schema o si risolvono i problemi aperti.

## 1. Cos'è questa applicazione

UnicoBPM è un motore di **Business Process Management (BPM)** su Laravel 13 + Filament 5, ad uso di
mediatori creditizi. Gestisce "pratiche" (istanze di processo) che avanzano attraverso una sequenza di
task, con assegnazione automatica basata su una matrice RACI per funzione aziendale, checklist di
raccolta dati con regole di respingimento (KO), promemoria/escalation SLA, e integrazione con sistemi
anagrafici e documentali esterni.

L'applicazione è interamente pilotata da Filament (nessun frontend pubblico eccetto il redirect `/`
verso `/admin`): non ci sono Controller applicativi oltre lo scheletro base.

## 2. Architettura multi-database

Questo è il punto più importante da capire prima di scrivere qualunque query o migration: **i model
non vivono tutti sulla stessa connessione**. Confondere le tre aree è la causa principale dei bug
trovati durante la rianalisi (foreign key cross-database, nomi di colonna copiati dal sistema sbagliato).

| Connessione | Database | Cosa ci vive | Note |
|---|---|---|---|
| `mysql` (default) | `unicobpm` | Il motore BPM vero e proprio: `Process`, `ProcessTask`, `ProcessTaskItem`, `ProcessTaskRaci`, `ProcessInstance`, `ProcessTaskExecution`, `ProcessTaskItemAnswer`, `ProcessTrigger`, `Checklist*`, `BusinessFunction`, `BusinessFunctionMember`, `User`, oltre alla tabella `activity_log` del plugin di audit. | Le migration in `database/migrations/` gestiscono **solo** queste tabelle. |
| `proforma` | `proforma` | Anagrafiche mediatori/clienti: `Client`, `Clienti` (namespace `App\Models\PROFORMA`), `Fornitore`. | Gestito da un altro sistema. Non creare migration Laravel per queste tabelle in questo repo. |
| `mysql_unicooam` | `unicooam` | DMS documentale e altre anagrafiche: `Document`, `DocumentType`, `EmailTemplate`, `Employee`, `App\Models\UNICOOAM\Company`, `App\Models\UNICOOAM\Task`, `App\Models\UNICOOAM\TaskDocumentType`. | Idem: sistema esterno, schema non modificabile da qui. |

**Regola pratica**: se un model dichiara `protected $connection = 'proforma'` o `'mysql_unicooam'`,
il suo schema **non è definito nelle migration di questo repository** e **non può essere referenziato
con una vera foreign key** da una tabella `unicobpm` (MySQL non supporta FK cross-database nel modo in
cui Laravel le genera con `->constrained()`). Se serve un legame, usare una colonna
`unsignedBigInteger`/`uuid` semplice con indice, senza vincolo — esattamente come già fatto per
`process_task_items.document_type_id` e `process_task_item_answers.document_id`.

Prima di scrivere codice che tocca `Document`, `DocumentType`, `EmailTemplate`, `Client*`, `Fornitore`:
**leggere il model per i nomi di colonna reali**, non assumerli per analogia con il resto del motore
BPM. Esempio concreto del bug trovato: il codice creava `Document` con `subject_type`/`subject_id`/
`file_path`, ma i campi reali sono `documentable_type`/`documentable_id`/`document_url`.

## 3. Modello di dominio (motore BPM, connessione `unicobpm`)

```
Process (template di processo)
 ├─ ProcessTask (step ordinati per "ordine", NON "order")
 │   ├─ ProcessTaskItem (azioni dentro lo step: document_upload, automated_email, validation_rule, ...)
 │   └─ ProcessTaskRaci (chi è R/A/C/I per questo task, per business_function_id)
 └─ ProcessInstance (una "pratica" reale)
     ├─ subject (polimorfo, NULLABLE: pratiche interne/ricorrenti non hanno un soggetto)
     ├─ currentTask → ProcessTask
     ├─ ProcessTaskExecution (una riga per ogni task attraversato dalla pratica)
     └─ ProcessTaskItemAnswer (risposte alle singole azioni di un task)

BusinessFunction (funzione aziendale / ufficio)
 ├─ employees()/clients() → membership polimorfica via business_function_members (member_type/member_id)
 └─ loginUsers() → utenti applicativi (User) risolti tramite la relazione user() su Employee/Client

Checklist → ChecklistItem (con regole KO) → ChecklistAnswer (per ProcessInstance)
ProcessTrigger → avvia un Process quando un modello configurato soddisfa condizioni (created/updated/idle)
```

### Convenzioni di naming da NON violare

Questi nomi sono stati causa di bug reali (query che referenziano una colonna inesistente, silenziosamente
o meno a seconda del contesto). Usare sempre questi, mai le varianti tra parentesi (trovate nel codice
prima della rianalisi):

- Ordinamento dei task: **`ordine`** (mai `order`).
- Ruolo RACI: colonna **`raci_role`**, valori **`R`/`A`/`C`/`I`** (mai `role_type`/`'responsible'`).
- Relazione funzioni aziendali su Employee/Client: **`businessFunctions()`** (camelCase; mai la stringa
  `'business_functions'` passata a `whereHas`/`whereIn` — Eloquent non converte snake→camel per te).
- Tabella pivot RACI: **`process_task_raci`** (mai `raci_assignments`, che non esiste).
- Stato pratica (`process_instances.status`, enum): **`pending`, `in_progress`, `completed`, `rejected`,
  `cancelled`, `suspended`** (mai `running` — non è nell'enum e genererebbe un errore SQL).
- Stato esecuzione task (`process_task_executions.execution_status`, string): **`pending`, `in_progress`,
  `completed`, `rejected_and_rewinded`** (colonna `execution_status`, non `status`).
- Ricorrenza processo (`processes`): flag **`is_periodic`** (mai `is_recurring`, non esiste); due
  meccanismi complementari di innesco: **`recurrence_frequency`** + **`recurrence_day`** per la
  ricorrenza a calendario, **`cron_expression`** per l'innesco basato su condizioni valutate
  periodicamente (vedi §7).
- Filtri dinamici sul modello target di un processo: **`trigger_filters`** (mai `target_filters`).

## 4. Motore di scheduling

Tre meccanismi coesistono in `routes/console.php` + `app/Console/Commands/`:

1. **`Schedule::command('bpm:run-scheduler')->dailyAt('06:00')`** — `BpmSchedulerCommand`: gestisce sia i
   trigger "idle" (pratiche di sollecito per record fermi da troppo tempo) sia i processi ricorrenti
   (`is_periodic` + `recurrence_frequency`/`recurrence_day`), chiamando `StartProcessAction::execute(null, $processId)`
   per i processi senza soggetto specifico.
2. **`Schedule::call(...)->everyMinute()`** in `routes/console.php` — rilancia `ExecutePeriodicProcessJob`
   per i processi con `next_run_at` nel passato (rischedulazioni manuali da pannello).
3. **`Schedule::job(new TaskEscalationWatchdogJob)->hourly()`** — controlla gli SLA sui task pendenti e
   applica le regole di escalation configurate su `process_tasks.escalation_rules`.

`DataAnomalyWatchdogJob` (scansione anagrafiche con P.IVA incompleta) **non è schedulato**: dipende da un
model `Customer` che non esiste in questo codebase (vive sul sistema esterno). Va ripreso quando
l'integrazione con quel sistema sarà definita.

`bpm:send-reminders` e `bpm:read-emails` **non sono schedulati**: dipendono rispettivamente da una
Mailable (`App\Mail\ReminderDocumentsMail`) e da una route pubblica firmata (`public.process.upload`) mai
create, e da configurazione IMAP (`config/imap.php`, variabili `IMAP_*`) mai pubblicata. Sono funzionalità
scritte ma incomplete, non bug di collegamento come lo erano gli Observer.

## 5. Audit log

Il tracciamento eventi usa **`alizharb/filament-activity-log`** (wrapper su `spatie/laravel-activitylog`),
tabella `activity_log`. Non esiste (e non va reintrodotto) un model custom tipo `ProcessInstanceLog`: è
stato usato e poi rimosso durante la rianalisi in favore di questo plugin.

Pattern da usare per loggare un evento di dominio:

```php
activity('bpm')
    ->causedBy(auth()->user())       // omettere se l'evento è generato dal sistema/da un job
    ->performedOn($processInstance)  // il subject polimorfo dell'attività
    ->event('nome_evento')           // es. instance_started, action_completed, task_advanced, escalation_triggered
    ->withProperties([...])          // payload arbitrario, finisce in properties (json)
    ->log('descrizione leggibile');  // finisce in description
```

`ProcessInstance::logs()` espone questi eventi come `morphMany(Activity::class, 'subject')`.

## 6. Confini di integrazione esterna — regole d'ingaggio

Prima di scrivere codice che coinvolge dati su `proforma` o `mysql_unicooam`:

1. **Leggere il model reale** per nomi di colonna/relazioni — non assumerli dai commenti né per analogia
   col motore BPM locale.
2. **Non creare foreign key Laravel (`->constrained()`)** verso tabelle su un'altra connessione: usare
   colonna + indice semplice.
3. **Non creare migration in questo repo per tabelle che vivono su `proforma`/`mysql_unicooam`**: quello
   schema è di competenza di un altro sistema.
4. Se una funzionalità richiede un model che non esiste in `app/Models` (es. `Customer`), **prima di
   crearlo in locale, verificare se concettualmente appartiene a uno dei due sistemi esterni**. Non è
   detto che vada creato qui: potrebbe dover arrivare via API/integrazione futura. In caso di dubbio,
   chiedere piuttosto che indovinare — un model locale creato per errore duplica una fonte di verità che
   dovrebbe restare esterna.
5. Al contrario, un riferimento a una classe interna al motore BPM (es. un log di audit, uno stato
   interno di workflow) **è quasi certamente un gap locale da colmare**, non un'integrazione esterna.

## 7. Problemi noti — stato dopo la rianalisi

### Risolti in questa sessione
- Import `MorphTo` mancante, scope RACI duplicate/incoerenti, Observer mai registrati, colonne
  `role_type`/`order`/`execution_status` sbagliate in `StartProcessAction`, `AdvanceProcessAction`,
  `User::getResponsibleUsersForInstance`, `ProcessTaskExecutionObserver`, `TaskEscalationWatchdogJob`.
- `ProcessInstance.subject_type`/`subject_id` erano `NOT NULL` ma il codice crea pratiche senza soggetto
  (processi interni/ricorrenti): resi nullable via migration.
- `Fornitore.company_id` hardcoded a un UUID fisso; auto-assegnazione da `auth()->user()` ripristinata
  su `Client`/`Fornitore`.
- Ledger delle migration disallineato dal DB reale (tabelle esistenti ma non registrate) e foreign key
  cross-database verso `documents`/`document_types`: sistemati.
- `Consultant` (model mai esistito) sostituito con `Client` ovunque referenziato: **`Client` è la
  tipologia "consulente esterno" coinvolta nelle attività RACI** (confermato) — non serve un model
  separato.
- Audit log migrato da model custom a `alizharb/filament-activity-log`.
- `BpmSchedulerCommand` usava `is_recurring` invece di `is_periodic`.
- **`processes.cron_expression`**: colonna aggiunta via migration. Il suo scopo (confermato) è valutare
  periodicamente delle condizioni (es. presenza/valore di un campo, tramite `trigger_field`/
  `trigger_state`/`trigger_value`/`trigger_filters` già esistenti sul model `Process`) per scatenare
  l'avvio del processo — un meccanismo complementare a `recurrence_frequency`/`recurrence_day`, non un
  duplicato. Il blocco in `routes/console.php` che la usa ora funziona (`Schedule::job(...)->cron(...)`
  per ogni processo con `cron_expression` valorizzato). **Nota**: la valutazione delle condizioni di
  trigger al momento dell'esecuzione non è ancora implementata in `ExecutePeriodicProcessJob` — il job
  oggi crea comunque le istanze per tutti i soggetti che passano `trigger_filters`, senza controllare
  `trigger_field`/`trigger_state`/`trigger_value` per singolo soggetto. Se serve quel controllo puntuale,
  va aggiunto esplicitamente.
- **`Process.target_filters` → `trigger_filters`**: corretto in `ExecutePeriodicProcessJob`.
- **`ProcessInstance.title`**: colonna aggiunta via migration (nullable, per le pratiche senza soggetto
  polimorfo) e inclusa nel `$fillable` del model.
- **`ProcessTask.days_to_complete`** letto da `StartProcessAction` per calcolare `due_at`, ma non è né
  in `$fillable` né in migration — oggi `due_at` è sempre `null`. Non ancora risolto: aggiungere la
  colonna se il calcolo scadenza per task è una funzionalità voluta.
- **`bpm:send-reminders`/`bpm:read-emails`**: mancano `App\Mail\ReminderDocumentsMail`, la route pubblica
  firmata `public.process.upload` (con relativo controller/vista), e `config/imap.php`. Funzionalità
  intenzionalmente non completate durante la rianalisi per non indovinare uno schema di upload pubblico
  senza requisiti chiari.
- Nessuna **Policy** Filament: l'accesso alle risorse del pannello non è scoperto per company/ruolo.
  Se l'app è multi-tenant (più mediatori sullo stesso DB), verificare se serve isolamento dati per
  `company_id` a livello di query dei Resource, non solo di default in creazione.

## 8. Linee guida operative per sessioni di coding assistito da AI

Queste regole nascono direttamente dai bug trovati in questa rianalisi — quasi tutti condividevano lo
stesso pattern: **codice scritto assumendo nomi di colonna plausibili invece di verificarli**.

1. **Non fidarsi dei commenti nel codice per i nomi di colonna/enum**: verificarli sempre contro la
   migration corrispondente o con `mcp__laravel-boost__database-schema` prima di scrivere una query.
2. **Se una query o un metodo referenzia una relazione/colonna, aprire il model target e controllare che
   esista davvero con quel nome esatto** (case-sensitive, `businessFunctions` ≠ `business_functions`).
3. **Prima di aggiungere una foreign key in una migration**, controllare la connessione del model target:
   se è diversa da quella della tabella che si sta creando, niente `->constrained()`.
4. **Testare le migration su un DB isolato prima di quello reale** (es. sqlite temporaneo via
   `DB_CONNECTION=sqlite DB_DATABASE=/tmp/x.sqlite php artisan migrate`), poi verificare lo stato del DB
   reale con `php artisan migrate:status` prima di lanciare `migrate --force` — questo codebase ha già
   avuto un disallineamento tra tabelle fisiche e ledger delle migration.
5. **Dopo aver scritto/modificato un'azione critica del motore BPM (`StartProcessAction`,
   `AdvanceProcessAction`, gli Observer), fare uno smoke test reale** (creare un `Process`/`ProcessTask`
   di prova, chiamare l'azione, verificare il risultato, ripulire i dati di prova) invece di fidarsi solo
   della lettura statica del codice: più bug qui erano invisibili a un semplice `php -l` o a Pint perché
   sintatticamente validi ma semanticamente sbagliati (nome colonna inesistente).
6. **Se una classe referenziata non esiste in `app/Models`**, non crearla per riflesso: prima capire se
   concettualmente appartiene al motore BPM locale o a uno dei sistemi esterni (§6), poi eventualmente
   chiedere se il dubbio persiste.
7. **Un comando/Job/Observer scritto ma non schedulato/registrato è un segnale d'allarme**, non
   necessariamente qualcosa da attivare a scatola chiusa: verificarne le dipendenze (classi, colonne,
   config) prima di collegarlo allo scheduler, altrimenti si trasforma un bug silenzioso (mai eseguito)
   in un errore rumoroso (eseguito e fallito) — a volte peggio per l'utente finale.

## 9. Testing

`tests/` contiene solo gli scheletri di default di Laravel (`ExampleTest` in Feature e Unit), senza
copertura reale del motore BPM. Prima di qualunque refactoring futuro su `StartProcessAction`,
`AdvanceProcessAction` o sugli Observer, andrebbero scritti almeno test Feature che coprano: avvio di
una pratica (con e senza soggetto), avanzamento al task successivo, rigetto per KO su checklist,
raggiungimento del completamento pratica. Al momento l'unica rete di sicurezza è lo smoke test manuale
descritto al punto 5 di §8.

## 10. Seeders

`database/seeders/` fornisce dati demo/di riferimento per il motore BPM (non anagrafiche esterne, che
restano di competenza di `proforma`/`mysql_unicooam`). Ordine di esecuzione in `DatabaseSeeder` rispetta
le dipendenze: anagrafiche di base (DocumentType, BusinessFunction, Checklist, Process) → dipendenze di
primo livello (ProcessTask, ChecklistItem) → RACI → processi completi (task + RACI + azioni):
`BpmDesignSeeder` (Onboarding Nuovo Agente) e `CreditBrokerProcessesSeeder` (AML, Trasparenza, OAM,
Istruttoria Pratica di Finanziamento — i processi operativi tipici di un mediatore creditizio).

**Attenzione alle collisioni tra seeder sullo stesso processo**: `ProcessTaskSeeder` +
`ProcessTaskRaciSeeder` seminano già i primi due task (ordine 10/20) di `PRC-AML` con una matrice RACI a
4 ruoli; `CreditBrokerProcessesSeeder::seedAmlProcess()` aggiunge solo il terzo step (ordine 30) senza
toccare quelli — un `updateOrCreate` chiave su `(process_id, ordine)` scritto da due seeder diversi sullo
stesso `ordine` sovrascrive silenziosamente dati dell'altro (è successo in fase di sviluppo: un RACI
`C` è stato riscritto a `R` per errore). Prima di aggiungere task a un processo già seminato altrove,
verificare quali `ordine` sono già occupati.

Tutti i seeder BPM sono scritti per essere **idempotenti** (`updateOrCreate`/`firstOrCreate` con una
chiave naturale — `code`, o `process_id`+`ordine` dove non esiste un `code`): rieseguire
`php artisan db:seed` non deve mai duplicare righe. Verificato eseguendo l'intera catena due volte di
seguito e confrontando i conteggi. Se si aggiunge un nuovo seeder BPM, mantenere questa proprietà: mai
`DB::table(...)->insert()` con ID hardcoded, mai `->create()`/`->createMany()` senza un controllo di
unicità a monte.

`ChecklistAnswerSeeder` e `VendorOnboardingProcessSeeder` sono stati **rimossi**: il primo inseriva dati
transazionali (risposte a una pratica specifica) come se fossero dati di riferimento statici, con colonne
che non esistono nello schema attuale; il secondo referenziava model (`Company`, `PrivacyDataType`) e
colonne (`company_id` su `processes`/`process_tasks`/`checklists`, `sequence_number`, `instruction`, ecc.)
che non sono mai esistiti in questo codebase — probabilmente residuo di un altro template/esperimento.
