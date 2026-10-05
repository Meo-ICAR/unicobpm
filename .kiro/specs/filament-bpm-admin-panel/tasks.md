# Piano di Implementazione: Filament BPM Admin Panel

## Panoramica

Implementazione del pannello amministrativo BPM su Filament v5.6 + Laravel 13. Si crea la struttura directory in `app/Filament/Resources`, si implementa `ProcessResource` con form, tabella e hook post-salvataggio, il `ProcessTasksRelationManager`, la relazione `businessFunction()` sul modello `ProcessTask`, e infine `ProcessInstanceResource` in sola lettura.

---

## Task

- [x] 1. Setup struttura directory e aggiunta relazione al modello
  - [x] 1.1 Creare la struttura di directory necessaria
    - Creare `app/Filament/Resources/ProcessResource/Pages/`
    - Creare `app/Filament/Resources/ProcessResource/RelationManagers/`
    - Creare `app/Filament/Resources/ProcessInstanceResource/Pages/`
    - _Requirements: 1.1, 1.2_

  - [x] 1.2 Aggiungere la relazione `businessFunction()` al modello `ProcessTask`
    - Aprire `app/Models/ProcessTask.php`
    - Aggiungere `use Illuminate\Database\Eloquent\Relations\BelongsTo;` se non presente
    - Aggiungere il metodo `businessFunction(): BelongsTo` che ritorna `$this->belongsTo(BusinessFunction::class)`
    - Aggiungere l'import `use App\Models\BusinessFunction;`
    - _Requirements: 5.5_

- [x] 2. Implementare ProcessResource (classe principale)
  - [x] 2.1 Creare `app/Filament/Resources/ProcessResource.php`
    - Definire `protected static ?string $model = Process::class`
    - Impostare `$navigationIcon = 'heroicon-o-cog-6-tooth'`, `$navigationGroup = 'BPM'`, `$navigationSort = 1`
    - Implementare `form(Form $form)` con tutte le sezioni: Identificazione (code, name, version, is_active), Descrizione (description), Target & Trigger (target_model, trigger_filters KeyValue, trigger_field/state/value), Esclusione (exclude_field/state/value), Periodicità (is_periodic Toggle live, cron_expression condizionale), Schedulazione (last_activated_at e next_run_at disabled)
    - `cron_expression` deve essere `->hidden(fn (Forms\Get $get) => ! $get('is_periodic'))` e `->required(fn (Forms\Get $get) => (bool) $get('is_periodic'))`
    - Implementare `table(Table $table)` con colonne: code, name, version, is_active (IconColumn), is_periodic (IconColumn), next_run_at (dateTime), updated_at (toggleable hidden)
    - Aggiungere filtri: `TernaryFilter` su `is_active` e `is_periodic`
    - Aggiungere azioni di riga: `EditAction`, `DeleteAction`; bulk action: `DeleteBulkAction`
    - `defaultSort('code')`
    - Implementare `getRelations()` restituendo `[ProcessTasksRelationManager::class]`
    - Implementare `getPages()` con index, create, edit
    - _Requirements: 2.1–2.10, 3.1–3.6, 7.1_

  - [x]* 2.2 Scrivere unit test per la visibilità condizionale di `cron_expression`
    - Verificare che `cron_expression` sia nascosto quando `is_periodic = false`
    - Verificare che `cron_expression` sia visibile e obbligatorio quando `is_periodic = true`
    - _Requirements: 2.7, 2.8, 2.9_

- [x] 3. Implementare le pagine Create e Edit con hook post-salvataggio
  - [x] 3.1 Creare `app/Filament/Resources/ProcessResource/Pages/ListProcesses.php`
    - Estendere `ListRecords`
    - Aggiungere `CreateAction` negli `headerActions`
    - _Requirements: 3.6_

  - [x] 3.2 Creare `app/Filament/Resources/ProcessResource/Pages/CreateProcess.php`
    - Estendere `CreateRecord`
    - Sovrascrivere `afterCreate()`: chiamare `$this->record->updateNextRunDate()`
    - _Requirements: 4.1_

  - [x] 3.3 Creare `app/Filament/Resources/ProcessResource/Pages/EditProcess.php`
    - Estendere `EditRecord`
    - Sovrascrivere `afterSave()`: chiamare `$this->record->updateNextRunDate()`
    - _Requirements: 4.1_

  - [x]* 3.4 Scrivere property test per `updateNextRunDate()` — Proprietà 1
    - **Proprietà 1: `updateNextRunDate()` imposta `next_run_at` per cron valide**
    - Per ogni espressione cron valida (es. `"0 1 * * *"`, `"*/5 * * * *"`) con `is_periodic = true`, dopo `updateNextRunDate()` il campo `next_run_at` deve essere non null e nel futuro
    - **Validates: Requirements 4.2**

  - [x]* 3.5 Scrivere property test per `updateNextRunDate()` — Proprietà 2
    - **Proprietà 2: `updateNextRunDate()` azzera `next_run_at` per cron non valide o `is_periodic = false`**
    - Per `is_periodic = false` oppure `cron_expression` nulla/non valida, dopo `updateNextRunDate()` il campo `next_run_at` deve essere `null`
    - **Validates: Requirements 4.3**

- [x] 4. Checkpoint — Verificare form e hook
  - Assicurarsi che tutti i test passino, chiedere all'utente se sorgono domande.

- [x] 5. Implementare ProcessTasksRelationManager
  - [-] 5.1 Creare `app/Filament/Resources/ProcessResource/RelationManagers/ProcessTasksRelationManager.php`
    - Impostare `protected static string $relationship = 'tasks'`
    - Impostare `protected static ?string $title = 'Task del Processo'`
    - Implementare `form(Form $form)` con sezioni: Identificazione Task (name obbligatorio, ordine numerico obbligatorio default 0, code opzionale, Select `business_function_id` con `->relationship('businessFunction', 'name')` searchable preload), Descrizione (Textarea opzionale), Trigger (trigger_field/state/value), Esclusione (exclude_field/state/value), Solleciti (has_reminders Toggle live, reminder_interval_days default 3 condizionale, max_reminders default 5 condizionale), Escalation (KeyValue `escalation_rules`)
    - Implementare `table(Table $table)` con colonne: ordine (sortable), name (searchable), businessFunction.name (placeholder '—'), has_reminders (IconColumn), reminder_interval_days (placeholder '—'), max_reminders (placeholder '—')
    - `defaultSort('ordine', 'asc')`
    - `headerActions`: `CreateAction`; azioni di riga: `EditAction`, `DeleteAction`; bulk: `DeleteBulkAction`
    - _Requirements: 5.1–5.12_

  - [x]* 5.2 Scrivere unit test per il RelationManager
    - Verificare che `has_reminders = false` nasconda `reminder_interval_days` e `max_reminders`
    - Verificare che `has_reminders = true` mostri i campi reminder
    - _Requirements: 5.8, 5.9_

- [x] 6. Implementare ProcessInstanceResource (sola lettura)
  - [-] 6.1 Creare `app/Filament/Resources/ProcessInstanceResource/Pages/ListProcessInstances.php`
    - Estendere `ListRecords`
    - Sovrascrivere `getHeaderActions()` restituendo `[]` (nessuna azione di creazione)
    - _Requirements: 6.1_

  - [-] 6.2 Creare `app/Filament/Resources/ProcessInstanceResource.php`
    - Impostare `protected static ?string $model = ProcessInstance::class`
    - Impostare `$navigationIcon = 'heroicon-o-play-circle'`, `$navigationGroup = 'BPM'`, `$navigationSort = 2`
    - Impostare label `'Istanza di Processo'` e pluralLabel `'Istanze di Processo'`
    - Implementare `form(Form $form)` restituendo `$form->schema([])` (non utilizzato)
    - Implementare `table(Table $table)` con colonne: id, process.name (searchable), status (BadgeColumn con colori: `gray`→pending, `info`→in_progress, `success`→completed, `danger`→rejected, `warning`→cancelled), subject_type (toggleable), colonna "Ultimo Log" con `getStateUsing` wrappato in `try/catch (\Throwable)` che accede a `$record->logs()->latest()->first()?->message ?? ''`, created_at (dateTime), completed_at (dateTime placeholder '—')
    - `defaultSort('created_at', 'desc')`
    - Filtri: `SelectFilter` su `status` con 5 valori, `SelectFilter` su `process_id` con `->relationship('process', 'name')` searchable preload
    - `->actions([])` e `->bulkActions([])` (sola lettura)
    - Implementare `getPages()` con solo `'index' => Pages\ListProcessInstances::route('/')`
    - _Requirements: 6.1–6.9, 7.2, 7.3_

  - [x]* 6.3 Scrivere property test per la colonna "Ultimo Log" — Proprietà 3
    - **Proprietà 3: Risoluzione del log non genera eccezioni**
    - Per qualsiasi `ProcessInstance` (con o senza log, con o senza modello `ProcessInstanceLog`), il `getStateUsing` deve restituire una stringa senza lanciare eccezioni
    - **Validates: Requirements 6.3**

  - [x]* 6.4 Scrivere property test per la mappatura badge — Proprietà 4
    - **Proprietà 4: Completezza della mappatura dei badge di status**
    - Per ogni valore dell'enum `status` (`pending`, `in_progress`, `completed`, `rejected`, `cancelled`), la mappa `->colors([...])` deve avere un colore definito e non null
    - **Validates: Requirements 6.5**

- [x] 7. Checkpoint finale — Verificare tutto
  - Assicurarsi che tutti i test passino e che le risorse siano visibili nel pannello `/admin`, chiedere all'utente se sorgono domande.

---

## Note

- I task contrassegnati con `*` sono opzionali e possono essere saltati per un MVP più rapido
- Ogni task fa riferimento ai requisiti specifici per la tracciabilità
- I checkpoint garantiscono la validazione incrementale
- I property test verificano le proprietà di correttezza formali definite nel design
- Il modello `ProcessTask` deve essere modificato **prima** di implementare il RelationManager (dipendenza su `businessFunction()`)
- Il pannello usa autodiscovery: non occorre modificare `AdminPanelProvider`
- La colonna "Ultimo Log" usa `try/catch (\Throwable)` per gestire il caso in cui `ProcessInstanceLog` non esiste ancora

---

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1"] },
    { "id": 2, "tasks": ["2.2", "3.1", "3.2", "3.3"] },
    { "id": 3, "tasks": ["3.4", "3.5", "5.1"] },
    { "id": 4, "tasks": ["5.2", "6.1"] },
    { "id": 5, "tasks": ["6.2"] },
    { "id": 6, "tasks": ["6.3", "6.4"] }
  ]
}
```
