# Requirements Document

## Introduzione

Questo documento descrive i requisiti per il pannello amministrativo BPM costruito con Filament v5.6 su Laravel 13. Il pannello espone tre macro-aree:

1. **ProcessResource** — anagrafica e configurazione dei processi aziendali (CRUD completo)
2. **ProcessTasksRelationManager** — gestione dei task come sotto-relazione del processo padre
3. **ProcessInstanceResource** — monitoraggio in sola lettura delle istanze di processo con filtri e ultimo log

Il pannello utilizza il meccanismo di autodiscovery di Filament puntando a `app/Filament/Resources` (namespace `App\Filament\Resources`), già configurato nell'`AdminPanelProvider`.

---

## Glossario

- **AdminPanel**: Il pannello Filament accessibile al percorso `/admin`, configurato in `AdminPanelProvider`.
- **Process**: Il model Eloquent `App\Models\Process` — anagrafica del processo aziendale (tabella `processes`).
- **ProcessTask**: Il model Eloquent `App\Models\ProcessTask` — fase sequenziale di un processo (tabella `process_tasks`).
- **ProcessInstance**: Il model Eloquent `App\Models\ProcessInstance` — istanza di esecuzione reale di un processo (tabella `process_instances`).
- **ProcessTaskRaci**: Il model Eloquent `App\Models\ProcessTaskRaci` — assegnazione di ruolo RACI a una funzione di business per un task.
- **BusinessFunction**: Il model Eloquent `App\Models\BusinessFunction` — funzione aziendale con `name` e `code`.
- **ProcessResource**: Classe Filament Resource che gestisce il CRUD di Process.
- **ProcessTasksRelationManager**: Classe Filament RelationManager embedded in ProcessResource che gestisce i ProcessTask figli.
- **ProcessInstanceResource**: Classe Filament Resource in sola lettura che espone le ProcessInstance.
- **trigger_filters**: Campo JSON su `processes` che contiene filtri chiave-valore applicati al `target_model` per selezionare i soggetti da processare.
- **cron_expression**: Stringa cron standard (5 campi) memorizzata su `processes.cron_expression`, usata per calcolare `next_run_at` tramite il metodo `updateNextRunDate()`.
- **escalation_rules**: Campo JSON su `process_tasks` che definisce i livelli di escalation e i tempi massimi di attesa.
- **Status ProcessInstance**: Insieme di valori enum ammessi per `process_instances.status`: `pending`, `in_progress`, `completed`, `rejected`, `cancelled`.
- **Ultimo Log**: L'ultimo record correlato nella relazione `logs()` di ProcessInstance, visualizzato come colonna nel listing.

---

## Requisiti

### Requisito 1 — Struttura del pannello e autodiscovery

**User Story:** Come amministratore, voglio che il pannello Filament carichi automaticamente tutte le risorse BPM senza dover registrare manualmente ogni classe, in modo da poter aggiungere nuove risorse senza modificare il provider.

#### Acceptance Criteria

1. THE AdminPanel SHALL caricare tutte le classi Resource presenti in `app/Filament/Resources` tramite il meccanismo `discoverResources` già configurato in `AdminPanelProvider`.
2. THE AdminPanel SHALL caricare tutte le classi RelationManager dalla directory `app/Filament/Resources` tramite autodiscovery Filament.
3. WHEN una nuova classe Resource viene creata in `app/Filament/Resources`, THE AdminPanel SHALL renderla disponibile nella navigazione senza modifiche all'`AdminPanelProvider`.

---

### Requisito 2 — Anagrafica processi: form di creazione e modifica (ProcessResource)

**User Story:** Come amministratore, voglio creare e modificare i processi aziendali con tutti i loro campi di configurazione, in modo da definire come e quando ogni processo deve essere eseguito.

#### Acceptance Criteria

1. THE ProcessResource SHALL esporre un form con i seguenti campi obbligatori: `code` (TextInput, univoco), `name` (TextInput), `version` (TextInput numerico, default 1), `is_active` (Toggle).
2. THE ProcessResource SHALL esporre nel form un campo `description` (Textarea, opzionale).
3. THE ProcessResource SHALL esporre nel form un campo `target_model` (Select, opzionale) che consente di scegliere il model Eloquent target del processo.
4. THE ProcessResource SHALL esporre nel form un campo `trigger_filters` (KeyValue o JsonEditor component, opzionale) per la configurazione dei filtri JSON applicati al `target_model`.
5. THE ProcessResource SHALL esporre nel form i campi `trigger_field`, `trigger_state`, `trigger_value` (TextInput, opzionali) per la configurazione del trigger sul campo del modello target.
6. THE ProcessResource SHALL esporre nel form i campi `exclude_field`, `exclude_state`, `exclude_value` (TextInput, opzionali) per l'esclusione condizionale di soggetti.
7. THE ProcessResource SHALL esporre nel form un Toggle `is_periodic` che controlla la visibilità condizionale del campo `cron_expression`.
8. WHEN `is_periodic` è abilitato, THE ProcessResource SHALL rendere visibile e obbligatorio il campo `cron_expression` (TextInput) nel form.
9. IF `is_periodic` è disabilitato, THEN THE ProcessResource SHALL nascondere il campo `cron_expression` nel form.
10. THE ProcessResource SHALL esporre nel form i campi `last_activated_at` e `next_run_at` (DateTimePicker, disabilitati in sola lettura) per la visibilità dello stato di schedulazione.

---

### Requisito 3 — Anagrafica processi: tabella di listing (ProcessResource)

**User Story:** Come amministratore, voglio visualizzare l'elenco dei processi con le informazioni principali e poter cercare, filtrare e ordinare, in modo da gestire rapidamente un catalogo di processi.

#### Acceptance Criteria

1. THE ProcessResource SHALL esporre una tabella con le colonne: `code`, `name`, `version`, `is_active` (IconColumn), `is_periodic` (IconColumn), `next_run_at` (DateTimeColumn), `updated_at`.
2. THE ProcessResource SHALL rendere la colonna `code` ricercabile tramite la funzionalità `searchable()` di Filament.
3. THE ProcessResource SHALL rendere la colonna `name` ricercabile tramite la funzionalità `searchable()` di Filament.
4. THE ProcessResource SHALL esporre un filtro booleano su `is_active` che consente di mostrare solo i processi attivi o solo quelli disattivati.
5. THE ProcessResource SHALL esporre un filtro booleano su `is_periodic` che consente di mostrare solo i processi periodici.
6. THE ProcessResource SHALL esporre un'azione di riga `EditAction` e un'azione di riga `DeleteAction`.

---

### Requisito 4 — Azione post-salvataggio: aggiornamento next_run_at

**User Story:** Come amministratore, voglio che la data di prossima esecuzione venga ricalcolata automaticamente ogni volta che salvo un processo, in modo da non dover eseguire operazioni manuali separate.

#### Acceptance Criteria

1. WHEN il form di creazione o modifica di ProcessResource viene salvato con successo, THE ProcessResource SHALL invocare il metodo `updateNextRunDate()` sul record `Process` salvato.
2. WHEN `updateNextRunDate()` viene invocato su un Process con `is_periodic = true` e `cron_expression` valida, THE Process SHALL aggiornare il campo `next_run_at` con la data calcolata dalla libreria CronExpression.
3. IF `is_periodic = false` oppure `cron_expression` non è valida, THEN THE Process SHALL impostare `next_run_at` a `null`.

---

### Requisito 5 — Gestione task come relazione del processo (ProcessTasksRelationManager)

**User Story:** Come amministratore, voglio gestire i task di un processo direttamente dalla pagina di dettaglio del processo, in modo da mantenere la configurazione dei task nel contesto del processo padre.

#### Acceptance Criteria

1. THE ProcessTasksRelationManager SHALL essere registrato come RelationManager di ProcessResource e visualizzato nella pagina di edit/view del processo padre.
2. THE ProcessTasksRelationManager SHALL esporre una tabella con le colonne: `ordine`, `name`, `business_function` (tramite relazione), `has_reminders` (IconColumn), `reminder_interval_days`, `max_reminders`.
3. THE ProcessTasksRelationManager SHALL consentire la creazione di un nuovo ProcessTask tramite un form modale con i seguenti campi obbligatori: `name` (TextInput), `ordine` (TextInput numerico).
4. THE ProcessTasksRelationManager SHALL esporre nel form di creazione/modifica del task i campi opzionali: `description` (Textarea), `code` (TextInput).
5. THE ProcessTasksRelationManager SHALL esporre nel form del task un Select `business_function_id` collegato tramite `->relationship('businessFunction', 'name')` alla tabella `business_functions`.
6. THE ProcessTasksRelationManager SHALL esporre nel form del task i campi `trigger_field`, `trigger_state`, `trigger_value` (TextInput, opzionali) per la condizione di attivazione del task.
7. THE ProcessTasksRelationManager SHALL esporre nel form del task i campi `exclude_field`, `exclude_state`, `exclude_value` (TextInput, opzionali) per l'esclusione condizionale.
8. THE ProcessTasksRelationManager SHALL esporre nel form del task un Toggle `has_reminders` che controlla la visibilità condizionale dei campi reminder.
9. WHEN `has_reminders` è abilitato, THE ProcessTasksRelationManager SHALL rendere visibili i campi `reminder_interval_days` (TextInput numerico, default 3) e `max_reminders` (TextInput numerico, default 5).
10. THE ProcessTasksRelationManager SHALL esporre nel form del task un campo `escalation_rules` (KeyValue o JsonEditor component, opzionale) per la configurazione delle regole di escalation JSON.
11. THE ProcessTasksRelationManager SHALL ordinare i task nella tabella per `ordine` ascendente come ordinamento di default.
12. THE ProcessTasksRelationManager SHALL esporre azioni di riga `EditAction` e `DeleteAction` per ogni task.

---

### Requisito 6 — Monitoraggio istanze di processo (ProcessInstanceResource)

**User Story:** Come amministratore, voglio visualizzare tutte le istanze di processo in esecuzione o completate con il loro stato corrente e l'ultimo log disponibile, in modo da monitorare lo stato operativo dei processi aziendali.

#### Acceptance Criteria

1. THE ProcessInstanceResource SHALL essere configurata in sola lettura, senza esporre azioni di creazione, modifica o cancellazione.
2. THE ProcessInstanceResource SHALL esporre una tabella con le colonne: `id`, `process.name` (tramite relazione), `status` (BadgeColumn), `subject_type`, `created_at`, `completed_at`.
3. THE ProcessInstanceResource SHALL esporre una colonna "Ultimo Log" che visualizza il campo `message` (o equivalente) dell'ultimo record dalla relazione `logs()` del ProcessInstance, con fallback a un valore vuoto se nessun log è presente.
4. THE ProcessInstanceResource SHALL esporre un filtro per `status` con i valori: `pending`, `in_progress`, `completed`, `rejected`, `cancelled`.
5. THE ProcessInstanceResource SHALL colorare la colonna `status` con badge di colore distinto per ciascun valore: `pending` (grigio), `in_progress` (blu), `completed` (verde), `rejected` (rosso), `cancelled` (arancio).
6. THE ProcessInstanceResource SHALL rendere la tabella ordinabile per `created_at` discendente come ordinamento di default.
7. THE ProcessInstanceResource SHALL esporre un filtro per `process_id` tramite Select collegato alla tabella `processes` per filtrare le istanze per processo.
8. WHEN l'amministratore seleziona un filtro di status, THE ProcessInstanceResource SHALL aggiornare la tabella mostrando solo le istanze con lo status selezionato.
9. THE ProcessInstanceResource SHALL rendere la colonna `process.name` ricercabile tramite la funzionalità `searchable()` di Filament con query sulla relazione.

---

### Requisito 7 — Navigazione e raggruppamento nel pannello

**User Story:** Come amministratore, voglio che le risorse BPM siano raggruppate logicamente nella navigazione laterale del pannello, in modo da orientarmi rapidamente tra le diverse sezioni.

#### Acceptance Criteria

1. THE ProcessResource SHALL essere assegnata a un gruppo di navigazione denominato "BPM" tramite la proprietà `$navigationGroup`.
2. THE ProcessInstanceResource SHALL essere assegnata al gruppo di navigazione "BPM" tramite la proprietà `$navigationGroup`.
3. THE AdminPanel SHALL visualizzare il gruppo "BPM" nella barra di navigazione laterale contenente i link a ProcessResource e ProcessInstanceResource.
