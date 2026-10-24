<!-- suite-unico:start -->
## La suite Unico (mediatori creditizi)

Questa app fa parte di una suite che condivide il database `core` e la libreria `meo-icar/unico-core`:

- **unicoagent**: preventivi e perfezionamento delle pratiche. App separata che parla con i vari CRM solo via API (gateway in `app/Services/Crm`: unicofinance, Mediafacile, REST generico).
- **unicofinance**: CRM e istruttoria (pratiche, clienti, documenti, agenti, istruttori; pannelli admin e agenti). Espone le API per unicoagent (`/api/agente/v1`, token e firma) e per unicobpm (`/api`, chiave `X-Api-Key`).
- **unicocoge** (ex Proforma): interfaccia COGE e contributi: provvigioni, proforma, fatture, prima nota.
- **unicooam**: compliance e organigramma (Dipendenti con i ruoli in `employee_roles`, documenti e scadenziario, OAM/IVASS, audit). I ruoli della suite si definiscono qui.
- **unicobpm**: BPM e coordinatore. Processi con RACI che chiamano via API le altre app (`config/services.php`, `apps`) e reagiscono a eventi esterni.
- **unico-core**: libreria comune per schema condiviso, documenti, email, firma, accesso e bridge `/api/commands`.
- Fuori da questo contesto, da non toccare: unicogdpr, ShareSync, backups3.

Regole valide in tutte le app:
- Lo schema condiviso si cambia solo in unico-core (migration con `unico-core:migrate`, protette da `hasTable`/`hasColumn`); le app non alterano a mano le tabelle del core. Id sempre interi.
- Istruttore = dipendente con il ruolo `istruttore` in `employee_roles`, come nell'organigramma di unicooam (in unicofinance: `App\Support\Istruttori`).
- Gli stati delle pratiche stanno in `pratica_stati` (flag `is_working`, `is_approved`, `is_rejected`, `is_suspended`...). La vecchia `pratiches_statos` non si usa più.
- Le app si parlano via API: chiavi solo nel `.env` (mai nel codice, nei log o nei commit) e nessuna lettura diretta delle tabelle di un'altra app se esiste un'API. Niente dati personali nei log.
- I database sono di sviluppo. Rispondi e scrivi i testi per gli utenti in italiano.
- Se esiste `graphify-out/GRAPH_REPORT.md`, consultalo prima di cercare nel codice; si aggiorna con `graphify update .`. Per l'estrazione semantica (documenti, PDF, immagini) graphify usa `GEMINI_API_KEY` dal `.env`: non stamparla né committarla.

## Questa app: unicobpm (BPM e coordinatore)
- Processi, task e RACI (`EmployeeType`): le azioni `external_check` (`GET /api/checks/{comando}`) e `external_command` chiamano le altre app via `ExternalAppClient`; gli indirizzi sono in `config/services.php` (`apps`).
- Chi riceve un avviso lo decide la RACI del passo; le app esterne forniscono solo dati e controlli, non inviano email.
- Non esegue le migration del core e i suoi seeder non vanno lanciati sul database condiviso. Specifiche in `BPM-DOMAIN-SPEC.md`.

### Manuali (consultali prima di rispondere)
- Manuale utente: `resources/manuals/manuale-operativo-bpm.html` (più `BPM-DOMAIN-SPEC.md`). Manuale tecnico: `resources/manuals/manuale-tecnico.html`.
- Ogni sezione ha un `id`: `grep -n 'id="' <file>` dà l'indice, poi leggi la sezione che serve. L'appendice «Fatti dal codice» del manuale tecnico (rotte, comandi, modelli, variabili `.env`) è generata: si rigenera con `python3 /var/www/html/unico-core/tools/manuals/gen_facts.py /var/www/html/unicobpm`; il resto è scritto a mano e va aggiornato quando cambia il comportamento.
- L'assistente (`App\Neuron\ManualAssistantAgent`) li indicizza con `php artisan manual:sync`: da rilanciare dopo ogni modifica.
<!-- suite-unico:end -->

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

## Chiamate verso le altre app
- **Tutte le chiamate partono da `ExternalAppClient`** (JSON, tempi massimi, `X-Api-Key` del bridge, `X-Correlation-Id`); l'identificativo di correlazione (`Correlation`) è quello della richiesta o uno per esecuzione e si ritrova nei log delle app.
- **Scritture e webhook passano da una coda** (`OutboundCallService` → `SendOutboundCall`, tabella `outbound_calls`): chiave di idempotenza, ritentativi con attesa crescente (1, 5, 15 min, 1 h, 4 h) per errori di rete, 5xx, 408/425/429; gli altri 4xx sono definitivi. Stato e «Riprova» in *Chiamate in uscita*. La scrittura di completamento di un processo (`completion_write_field`) usa questa coda e il registro attività si scrive alla consegna (`LogCompletionWrite`). Il controllo blacklist resta sincrono (serve la risposta).
- **Webhook per i terzi** (`WebhookEndpoint`, *Webhook*): `process.status_changed` a ogni cambio di stato di un'istanza, corpo `{event, occurred_at, data}`, firma `X-Unico-Signature: sha256=HMAC(corpo)` con il segreto del destinatario, solo https (http in sviluppo).
- **Comandi sulle altre app**: `bpm:dispatch-external-command {app} {comando} --option=nome=valore [--wait --timeout=600]` usa il bridge di unico-core (`/api/commands`, 202 con `run_id`); con `--wait` legge l'esito del lancio e fallisce se il comando fallisce.
- **Email dei processi**: template scelti per company dell'istanza, app `unicobpm`, tipo `processo` e grado (`EmailTemplateResolver`; senza il grado richiesto vale quello inferiore più vicino); la casella mittente è quella di tipo `processo` della company (`mail_accounts`), altrimenti il mailer di default.
- **Risposte email che fanno avanzare i processi**: le email dei processi partono con Message-ID `<bpm-{istanza}-{esecuzione}-{casuale}@dominio>` (`ProcessThread`; fallback `[ID: n]` nell'oggetto). Le caselle di tipo `processo` (`mail_accounts`) si scaricano ogni 5 minuti (`unico-core:mail-fetch --type=processo`); per ogni email in arrivo `HandleProcessEmailReply` risale al processo: gli allegati soddisfano le azioni di caricamento (`ProcessAttachmentIngestor`, lo stesso del vecchio `bpm:read-emails`), il testo risponde all'azione **«Attesa risposta email»** (`await_email_reply`; config: `question`, `min_confidence` 80, `on_refuse` review|answer|suspend, `auto`). Il testo (senza citazioni e firma) lo interpretano prima le regole (risposte brevi inequivocabili), poi l'AI (`EmailReplyAgent`, serve `ANTHROPIC_API_KEY`); sopra la soglia il processo avanza da solo (risposta con operatore `procedural` o `ai`), altrimenti la risposta aspetta in *Risposte email* (`process_email_replies`) dove un operatore conferma, registra il rifiuto o ignora. Ogni email si elabora una volta; una risposta a un passo già concluso si registra senza effetti.
- **Rinvii**: una risposta che chiede più tempo con una data (`postpone`) può spostare il termine dello step corrente senza farlo avanzare. Con `on_postpone: grant` si concede da soli, solo entro i limiti dell'azione (`max_postpone_days` 30 oltre il termine attuale, `max_postponements` 2 per step, mai oltre `hard_deadline_at` della pratica, data futura, confidenza ≥ soglia); altrimenti in *Risposte email* l'operatore usa «Concedi rinvio» (nuova data, anche spostando la data tassativa). Il rinvio scrive `process_task_executions.postponed_until` (`effectiveDueAt()` non va mai indietro, `isOverdue()` ne tiene conto, `TaskEscalationWatchdogJob` salta lo step fino a quella data), lascia una riga in `process_postponements` e conferma alla controparte per email (`confirm_postpone_email`).
- **Eventi dalle altre app**: `POST /api/events` (chiave `X-Api-Key` = `BPM_BRIDGE_API_KEY`, 503 se non configurata) con `{event_id, event, source?, subject_ref?, occurred_at?, data{}}`; ogni `event_id` si registra una sola volta (`external_events`, rinvio = 200 `duplicate`). I trigger di tipo **«Evento da un'altra app»** (`event_type=external`, `external_event`, condizioni sui dati, anche con percorsi come `product.type`) avviano il processo senza soggetto locale: l'istanza porta `title`, `external_ref` (`source:subject_ref`), `external_data` e la company se `data.company_vat` è una partita IVA nota. Esito dell'evento: `processed`, `ignored` (nessun trigger applicabile), `failed`.
- **Registro attività**: `activity_log` è una tabella del pacchetto, ma i modelli del processo scrivono sulla connessione predefinita; se il database di unicobpm è diverso da quello core (sviluppo) la migration `create_activity_log_if_missing` la crea anche qui (se esiste già non fa nulla). Un evento rimasto in errore risponde 503 (il mittente ritenta) e si rielabora al nuovo invio.
- **Eventi ricevuti** (menu BPM): elenco di tutto ciò che le altre app comunicano, con stato e processi avviati. `backup.esito` (da `backups3`, a fine run notturno: `data.status` ok|failed, `databases`, `databases_failed`, `databases_warnings`, `exit_codes`) ha una riga di sintesi. Per essere avvisati di un backup fallito basta un trigger «Evento da un'altra app» su `backup.esito` con la condizione `status = failed` che avvii un processo di intervento.
- **Whistleblowing** (`WhistleblowingProcessSeeder`, idempotente; `php artisan db:seed --class=WhistleblowingProcessSeeder`): crea i ruoli (uffici) **«responsabile whistleblowing»** (codice `WHISTLEBLOWING`) e **«supplente whistleblowing»** (`WHISTLEBLOWING-SUPPLENTI`), i template `WHISTLE_NUOVA_SEGNALAZIONE` e `WHISTLE_SOLLECITO`, il processo `PRC-WHISTLE` (presa in carico → riscontro) e il trigger sull'evento `segnalazione.ricevuta` di UnicoWhistle. All'arrivo l'azione `automated_email` (`target_business_function` = codice del ruolo, `to_role` facoltativo = nome di un altro ruolo) scrive a chi ha il ruolo in `employee_roles` (dipendenti ed esterni); se nessuno, all'email dell'ufficio, senza contenuto della segnalazione; la data tassativa è `data.hard_deadline` dell'evento. Il ruolo si assegna nella scheda del dipendente o dell'esterno (campo Ruoli).
- **Solleciti di presa in carico**: `TaskEscalationWatchdogJob` ora manda le email (`EscalationNotifier`): `notify_assignee` a chi ha in carico o, se nessuno, a chi ha i ruoli R del task; `notify_manager` a `manager_email` o a chi ha i ruoli A; `reassign` al ruolo `target_business_function` (id di `employee_types`). Template `email_template_code` (default `TASK_ESCALATION`) per company/app/tipo `processo`/grado (livello 1 regular, 2 warning, 3+ alert); segnaposto `{pratica} {riferimento} {task} {ore} {livello}`. Con `until_claimed: true` nella regola vale solo finché nessuno prende in carico (anche per passi già «in_progress»); «Prendi in carico» valorizza `claimed_at` e ferma i solleciti. I passi con rinvio valido sono saltati.
- **Ruoli e uffici**: le funzioni aziendali sono confluite in `employee_types` (migration `merge_business_functions_into_employee_types`, corrispondenza in `EmployeeType::OFFICE_ROLES`). Il ruolo ha codice, area, email dell'ufficio, esternalizzabile (`outsourcable_status` yes/partial: può essere ricoperto anche da un esterno) e responsabile (`manager`, dipendente o esterno). Chi ricopre un ruolo lo ha in `employee_roles` di `employees`/`clients`; matrice RACI e passi puntano a `employee_type_id`. Le vecchie tabelle `business_functions` restano solo per storico. Nei seeder: `EmployeeType::office($codice, ['name' => ...])`.

