# Graph Report - unicobpm  (2026-10-05)

## Corpus Check
- 433 files · ~197,800 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2114 nodes · 4546 edges · 175 communities (76 shown, 99 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 41 edges (avg confidence: 0.83)
- Token cost: 72,626 input · 0 output

## Community Hubs (Navigation)
- Email Templates
- List Pages
- Create Pages
- SegretariaAiDraftConfirmationTest
- SsoTokenApiControllerTest
- Checklist Tables
- Checklist & Company Models
- Seeders
- Login Role Resolution
- External Records Commands
- Form Configurators
- Business Function Resource
- Chat & ProcessInstance
- Clients & Business Functions
- Document Type Form
- Start/Advance Process Actions
- Checklist Item Resource
- Create/Edit Process
- Permissions Relation Manager
- ProcessTask & Plan Access
- Permissions & Resource Base
- RACI Matrix
- Task Item Answers
- Process (core model)
- Function Members
- Admin Panel Provider
- ProcessTaskItem & Doc Classifier
- Incoming Emails & Reminders
- Severity & Example Tests
- Fornitori/Clienti/Employees
- Employees/Clients Relation Mgrs
- Checklist Resource
- ProcessTask Resource
- BPM Design Seeders
- Dynamic Group Export
- Checklist Answers Model
- Process Relation Managers
- Periodic & Escalation Jobs
- External Check & Chat
- ProcessInstance Resource
- ProcessTrigger Resource
- Document Types Resource
- Tasks Resource
- Assistente AI & Answer Form
- BPM API Controllers
- BPM Knowledge Base & Classifier Agent
- Document Classifier Tests
- Checklist Answer Resource
- Chat Message & Search Tool
- ProcessInstance Observer & Tests
- Dashboard Widgets
- Scoped MySQL Tools
- UserRole & Presets Seeder
- Resource Presets Relation Mgr
- Document Model
- Database Seeder & AI Smoke Test
- Filament Form Schemas
- AI Agents (Segretaria/Navigator)
- PlanType & Helpers
- Task Items Relation Mgr
- Mandatory Deadline Watchdog
- Process Triggers
- BPM Scheduler & Advance
- Segretaria AI
- Reminder Email Tool
- Search Past Conversations Tool
- Data Navigator Agent
- SSO Token Broker
- Edit Pages
- View Pages
- User Factory
- Manual Assistant Agent
- Process Form
- Migration: create cache table
- Migration: add completion write fields to processes table
- Migration: add completion write app to processes table
- Migration: add checklist id to process task items table
- Migration: add employee type id to business function members table
- Migration: add role to users table
- Migration: add operator tracking to process task item answers table
- Migration: add employee roles to fornitoris table
- Migration: add employee roles to clients table
- Migration: add severity to email templates table
- Migration: create employee types table
- Migration: create companies table
- Migration: create processes table
- Migration: create business functions table
- Migration: create checklists table
- Migration: create process task items table
- Migration: create process istances table
- Migration: create checklist answers table
- Migration: create checklist submissions table
- Migration: create process task raci table
- Migration: create process task executions table
- Migration: create process task item answers table
- Migration: create resourcess table
- Migration: create employee type permissions table
- Migration: create chat messages table
- Migration: create tasks table
- Migration: create task document types table
- Migration: create ai action drafts table
- Migration: create modules table
- Migration: create employee type resource presets table
- Business Function/Trigger Migrations
- Schema Migrations
- Base Migrations
- Item Forms
- ProcessInstance Form
- Activity Log Setup
- ProcessTasks Table
- App Bootstrap
- Data Navigator Agent (2)
- User Model
- Cron Expression Migration
- composer 1
- segretaria-ai
- composer 2
- mcp
- .mcp
- app-switcher-widget
- Frontend Build Config
- Composer Dependencies
- Composer Packages
- Composer Packages (2)
- Composer Packages (3)
- Composer Packages (4)
- BPM-DOMAIN-SPEC 1
- BPM-DOMAIN-SPEC 2
- Kiro Admin Panel Spec
- Domain Spec & Manual
- Manual & Domain Spec (ops)
- Agent Guidelines & README
- Spec Requirements

## God Nodes (most connected - your core abstractions)
1. `Process` - 83 edges
2. `ProcessInstance` - 80 edges
3. `ProcessTask` - 50 edges
4. `TestCase` - 48 edges
5. `HasPlanAccess` - 45 edges
6. `BusinessFunction` - 40 edges
7. `Resource` - 39 edges
8. `ProcessTaskExecution` - 38 edges
9. `ProcessTaskItem` - 38 edges
10. `EmployeeType` - 35 edges

## Surprising Connections (you probably didn't know these)
- `Filament BPM Admin Panel Design` --conceptually_related_to--> `Filament v5 conventions`  [INFERRED]
  .kiro/specs/filament-bpm-admin-panel/design.md → AGENTS.md
- `ProcessTask` --implements--> `ProcessTasksRelationManager`  [INFERRED]
  BPM-DOMAIN-SPEC.md → .kiro/specs/filament-bpm-admin-panel/design.md
- `Process (process template)` --implements--> `ProcessResource`  [INFERRED]
  BPM-DOMAIN-SPEC.md → .kiro/specs/filament-bpm-admin-panel/design.md
- `ProcessInstance (pratica)` --implements--> `ProcessInstanceResource (read-only)`  [INFERRED]
  BPM-DOMAIN-SPEC.md → .kiro/specs/filament-bpm-admin-panel/design.md
- `ProcessResource` --conceptually_related_to--> `Filament admin panel`  [INFERRED]
  .kiro/specs/filament-bpm-admin-panel/design.md → resources/manuals/manuale-operativo-bpm.html

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Process template to execution flow** — bpm_domain_spec_process, bpm_domain_spec_processtask, bpm_domain_spec_processinstance, bpm_domain_spec_processtaskexecution, bpm_domain_spec_processtaskitemanswer [EXTRACTED 0.95]
- **Spec to design to tasks pipeline for Filament resources** — kiro_specs_filament_bpm_admin_panel_design_processresource, kiro_specs_filament_bpm_admin_panel_requirements_processresource, kiro_specs_filament_bpm_admin_panel_tasks_processresource [INFERRED 0.85]

## Communities (175 total, 99 thin omitted)

### Community 0 - "Email Templates"
Cohesion: 0.08
Nodes (11): EmailTemplate, ProxiedGeminiEmbeddingsProvider, CheckStaleExternalRecordsTest, ExternalCheckRunnerTest, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}() (+3 more)

### Community 10 - "List Pages"
Cohesion: 0.10
Nodes (9): ListChecklistAnswers, ListDocumentTypes, ListEmailTemplates, ListEmployeeTypes, ListProcessTaskExecutions, ListProcessTaskItems, ListResources, ListTasks (+1 more)

### Community 11 - "Create Pages"
Cohesion: 0.09
Nodes (10): CreateBusinessFunction, CreateEmailTemplate, CreateEmployeeType, CreateProcessTaskExecution, CreateProcessTaskItem, ProcessTaskItemResource, CreateResource, CreateTask (+2 more)

### Community 12 - "Checklist Tables"
Cohesion: 0.11
Nodes (7): EmployeeTypesTable, ProcessTaskExecutionsTable, ProcessTaskItemsTable, ResourcesTable, UsersTable, {closure#1}(), {closure#2}()

### Community 13 - "Checklist & Company Models"
Cohesion: 0.09
Nodes (3): Company, FornitoriRole, Module

### Community 14 - "Seeders"
Cohesion: 0.09
Nodes (9): BusinessFunctionSeeder, ChecklistItemSeeder, ChecklistSeeder, DocumentTypeSeeder, ModuleSeeder, ProcessTaskRaciSeeder, ScadenziarioCogeProcessSeeder, TaskDocumentTypeSeeder (+1 more)

### Community 15 - "Login Role Resolution"
Cohesion: 0.09
Nodes (4): ResolveCompanyPlanTypeOnLogin, ResolveUserRoleOnLogin, CompanyModule, AppServiceProvider

### Community 16 - "External Records Commands"
Cohesion: 0.10
Nodes (5): CheckStaleExternalRecords, DispatchExternalCommand, ProcessTaskExecutionObserver, EmailSendingService, ExternalAppResolver

### Community 17 - "Form Configurators"
Cohesion: 0.09
Nodes (7): ChecklistForm, {closure#1}(), {closure#2}(), {closure#3}(), {closure#5}(), {closure#1}(), {closure#4}()

### Community 18 - "Business Function Resource"
Cohesion: 0.10
Nodes (7): BusinessFunctionResource, ListBusinessFunctions, BusinessFunctionForm, BusinessFunctionsTable, DocumentTypesTable, EmailTemplatesTable, TasksTable

### Community 19 - "Chat & ProcessInstance"
Cohesion: 0.11
Nodes (9): Employee, {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}() (+1 more)

### Community 2 - "Clients & Business Functions"
Cohesion: 0.07
Nodes (4): Client, Employee, {closure#1}(), {closure#1}()

### Community 20 - "Document Type Form"
Cohesion: 0.09
Nodes (8): FormHelper, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#1}()

### Community 21 - "Start/Advance Process Actions"
Cohesion: 0.12
Nodes (5): ProcessTaskExecution, BlacklistCheckObserverTest, ProcessTaskExecutionDeadlineTest, {closure#1}(), {closure#1}()

### Community 22 - "Checklist Item Resource"
Cohesion: 0.11
Nodes (6): ChecklistItemResource, CreateChecklistItem, EditChecklistItem, ListChecklistItems, ChecklistItemForm, ChecklistItemsTable

### Community 23 - "Create/Edit Process"
Cohesion: 0.11
Nodes (7): CreateProcess, EditProcess, ListProcesses, ProcessResource, ProcessTasksRelationManager, ProcessForm, ProcessesTable

### Community 24 - "Permissions Relation Manager"
Cohesion: 0.16
Nodes (15): PermissionsRelationManager, EmployeeType, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}() (+7 more)

### Community 25 - "ProcessTask & Plan Access"
Cohesion: 0.11
Nodes (5): AiActionDraft, {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}()

### Community 26 - "Permissions & Resource Base"
Cohesion: 0.18
Nodes (17): PermissionsRelationManager, Resource, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}() (+9 more)

### Community 27 - "RACI Matrix"
Cohesion: 0.14
Nodes (7): ProcessRaciMatrix, ProcessTask, ProcessTaskSeeder, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 28 - "Task Item Answers"
Cohesion: 0.12
Nodes (4): ProcessTaskItemAnswer, ProcessTaskItemAnswerObserver, ProcessTaskExecutionOperatorTrackingTest, RecordingTestJob

### Community 29 - "Process (core model)"
Cohesion: 0.11
Nodes (5): Process, ComplianceDocumentazioneProcessSeeder, ProcessSeeder, BpmActivitiesControllerTest, CompletionWriteBackTest

### Community 3 - "Function Members"
Cohesion: 0.07
Nodes (7): BusinessFunctionMember, ChecklistSubmission, EmployeeTypePermission, EmployeeTypeResourcePreset, ProcessTaskRaci, TaskDocumentType, {closure#1}()

### Community 32 - "ProcessTaskItem & Doc Classifier"
Cohesion: 0.12
Nodes (6): ProcessTaskItem, {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 33 - "Incoming Emails & Reminders"
Cohesion: 0.11
Nodes (4): ProcessIncomingEmails, SendProcessReminders, SyncManualAssistant, SyncResourcesCommand

### Community 34 - "Severity & Example Tests"
Cohesion: 0.17
Nodes (8): Severity, ExampleTest, SeverityTest, {closure#1}(), Alert, Ok, Regular, Warning

### Community 38 - "Employees/Clients Relation Mgrs"
Cohesion: 0.15
Nodes (7): ClientsRelationManager, EmployeesRelationManager, {closure#1}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 39 - "Checklist Resource"
Cohesion: 0.14
Nodes (6): ChecklistResource, CreateChecklist, EditChecklist, ListChecklists, ChecklistItemRelationManager, ChecklistsTable

### Community 40 - "ProcessTask Resource"
Cohesion: 0.14
Nodes (6): CreateProcessTask, EditProcessTask, ListProcessTasks, ProcessTaskResource, ProcessTaskForm, ProcessTasksTable

### Community 41 - "BPM Design Seeders"
Cohesion: 0.26
Nodes (5): BusinessFunction, Checklist, DocumentType, BpmDesignSeeder, CreditBrokerProcessesSeeder

### Community 43 - "Checklist Answers Model"
Cohesion: 0.16
Nodes (4): ChecklistAnswer, ChecklistItem, ChecklistAnswerObserver, {closure#6}()

### Community 44 - "Process Relation Managers"
Cohesion: 0.14
Nodes (4): ChecklistAnswersRelationManager, TaskExecutionsRelationManager, ProcessTaskItemsRelationManager, HasRelationPlanAccess

### Community 45 - "Periodic & Escalation Jobs"
Cohesion: 0.23
Nodes (4): DataAnomalyWatchdogJob, ExecutePeriodicProcessJob, TaskEscalationWatchdogJob, {closure#1}()

### Community 48 - "ProcessInstance Resource"
Cohesion: 0.16
Nodes (6): CreateProcessInstance, EditProcessInstance, ListProcessInstances, ProcessInstanceResource, ProcessInstanceForm, ProcessInstancesTable

### Community 49 - "ProcessTrigger Resource"
Cohesion: 0.16
Nodes (6): CreateProcessTrigger, EditProcessTrigger, ListProcessTriggers, ProcessTriggerResource, ProcessTriggerForm, ProcessTriggersTable

### Community 5 - "Document Types Resource"
Cohesion: 0.12
Nodes (9): DocumentTypeResource, CreateDocumentType, EditDocumentType, EmailTemplateResource, EmployeeTypeResource, ProcessTaskExecutionResource, ResourceResource, TaskResource (+1 more)

### Community 50 - "Tasks Resource"
Cohesion: 0.13
Nodes (3): Task, {closure#1}(), {closure#12}()

### Community 53 - "BPM API Controllers"
Cohesion: 0.19
Nodes (5): BpmActivitiesController, SsoTokenApiController, Controller, {closure#1}(), {closure#2}()

### Community 55 - "Document Classifier Tests"
Cohesion: 0.26
Nodes (3): DocumentClassificationResult, DocumentClassifier, DocumentClassifierTest

### Community 57 - "Checklist Answer Resource"
Cohesion: 0.19
Nodes (5): ChecklistAnswerResource, CreateChecklistAnswer, EditChecklistAnswer, ChecklistAnswerForm, ChecklistAnswersTable

### Community 59 - "Chat Message & Search Tool"
Cohesion: 0.20
Nodes (4): ChatMessage, ChatMessageTest, {closure#2}(), {closure#3}()

### Community 6 - "ProcessInstance Observer & Tests"
Cohesion: 0.09
Nodes (8): AppSwitcherWidgetTest, DispatchExternalCommandTest, ExampleTest, ProcessEligibleRecordsCountTest, TestEligibleSubject, ProcessInstanceSubjectColumnTest, RemindersWidgetTest, TestCase

### Community 62 - "Dashboard Widgets"
Cohesion: 0.19
Nodes (3): AppSwitcherWidget, RemindersWidget, {closure#1}()

### Community 66 - "UserRole & Presets Seeder"
Cohesion: 0.24
Nodes (8): UserRole, EmployeeTypeResourcePresetSeeder, ADMIN, INSPECTOR, QUALITY, SOS, SUPER_ADMIN, USER

### Community 67 - "Resource Presets Relation Mgr"
Cohesion: 0.18
Nodes (7): ResourcePresetsRelationManager, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 69 - "Database Seeder & AI Smoke Test"
Cohesion: 0.19
Nodes (3): User, DatabaseSeeder, AssistenteAiSmokeTest

### Community 7 - "Filament Form Schemas"
Cohesion: 0.08
Nodes (9): DocumentTypeForm, EmailTemplateForm, EmployeeTypeForm, EmployeeTypeInfolist, ProcessTaskExecutionForm, ResourceForm, ResourceInfolist, TaskForm (+1 more)

### Community 72 - "PlanType & Helpers"
Cohesion: 0.24
Nodes (9): PlanType, checkPiano(), effectivePlanType(), resolvePianoAccess(), resolveUserEmployeeTypeIds(), Base, Full, Medium (+1 more)

### Community 76 - "Task Items Relation Mgr"
Cohesion: 0.20
Nodes (7): ProcessTaskItemForm, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 8 - "BPM Scheduler & Advance"
Cohesion: 0.09
Nodes (13): BpmSchedulerCommand, AdvanceProcessAction, CompleteCurrentTaskAction, StartProcessAction, ProcessInstance, ProcessInstanceObserver, ProcessInstanceHardDeadlineTest, {closure#1}() (+5 more)

### Community 9 - "Edit Pages"
Cohesion: 0.10
Nodes (8): EditBusinessFunction, EditEmailTemplate, EditEmployeeType, EditProcessTaskExecution, EditProcessTaskItem, EditResource, EditTask, EditUser

### Community 1 - "Process Form"
Cohesion: 0.09
Nodes (33): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}(), {closure#17}() (+25 more)

### Community 31 - "Business Function/Trigger Migrations"
Cohesion: 0.09
Nodes (6): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}()

### Community 36 - "Schema Migrations"
Cohesion: 0.10
Nodes (5): {closure#1}(), {closure#1}(), {closure#1}(), {closure#1}(), {closure#2}()

### Community 4 - "Base Migrations"
Cohesion: 0.08
Nodes (23): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}() (+15 more)

### Community 52 - "Item Forms"
Cohesion: 0.12
Nodes (9): {closure#1}(), {closure#4}(), {closure#1}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 61 - "ProcessInstance Form"
Cohesion: 0.14
Nodes (3): {closure#1}(), {closure#11}(), {closure#6}()

### Community 65 - "Activity Log Setup"
Cohesion: 0.19
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), columnIndexes(), indexes()

### Community 82 - "ProcessTasks Table"
Cohesion: 0.20
Nodes (4): {closure#2}(), {closure#4}(), {closure#6}(), {closure#8}()

### Community 91 - "App Bootstrap"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 98 - "Cron Expression Migration"
Cohesion: 0.29
Nodes (4): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}()

### Community 101 - "composer 1"
Cohesion: 0.33
Nodes (6): autoload, files, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 138 - "composer 2"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 47 - "Frontend Build Config"
Cohesion: 0.12
Nodes (17): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, private, $schema (+9 more)

### Community 56 - "Composer Dependencies"
Cohesion: 0.12
Nodes (16): require, alizharb/filament-activity-log, dutchcodingcompany/filament-socialite, filament/filament, filament/spatie-laravel-media-library-plugin, html2text/html2text, laravel/framework, laravel/socialite (+8 more)

### Community 74 - "Composer Packages"
Cohesion: 0.17
Nodes (11): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+3 more)

### Community 85 - "Composer Packages (2)"
Cohesion: 0.20
Nodes (10): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-commit, pre-package-uninstall (+2 more)

### Community 88 - "Composer Packages (3)"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+1 more)

### Community 97 - "Composer Packages (4)"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 100 - "BPM-DOMAIN-SPEC 1"
Cohesion: 0.33
Nodes (6): BusinessFunction, ProcessTask, ProcessTaskItem, ProcessTaskRaci (RACI matrix), Claim of pratica by function member, RACI matrix and auto-assignment

### Community 102 - "BPM-DOMAIN-SPEC 2"
Cohesion: 0.40
Nodes (5): Process (process template), ProcessInstance (pratica), ProcessTaskExecution, ProcessTaskItemAnswer, ProcessTrigger

### Community 37 - "Kiro Admin Panel Spec"
Cohesion: 0.15
Nodes (21): Filament autodiscovery of resources, ProcessInstanceResource (read-only), updateNextRunDate post-save hook, No new composer dependencies, ProcessResource, Correctness properties, ProcessTasksRelationManager, escalation_rules (+13 more)

### Community 64 - "Domain Spec & Manual"
Cohesion: 0.16
Nodes (14): Audit log via filament-activity-log, Client as external consultant in RACI, proforma connection (external), mysql_unicooam connection (external), External integration rules of engagement, ExternalAppResolver config gap, HasBpmTriggers evaluateBpmConditions bugs, Known issues and TODOs (+6 more)

### Community 80 - "Manual & Domain Spec (ops)"
Cohesion: 0.27
Nodes (11): Checklist and KO rules, Task item action types, AssistenteAI, Checklist, Istanza di processo / pratica, Item del task, Knock-out rules, Pratica lifecycle (+3 more)

### Community 89 - "Agent Guidelines & README"
Cohesion: 0.25
Nodes (8): Filament v5 conventions, Laravel Boost guidelines, PHPUnit testing rules, Pint formatting rule, AI-assisted coding guidelines, Naming conventions (ordine, raci_role, is_periodic), AGENTS.md Laravel Boost guidelines, README (Laravel default)

### Community 92 - "Spec Requirements"
Cohesion: 0.25
Nodes (8): cron_expression vs recurrence_frequency, No queue worker running (open TODO), Scheduling engine (bpm:run-scheduler, watchdogs), trigger_filters, Periodic/recurring processes, Conditional task skipping, Reminders and SLA escalation, Trigger (three levels)

## Knowledge Gaps
- **93 isolated node(s):** `files`, `App\\`, `Database\\Factories\\`, `Database\\Seeders\\`, `cancelDraft({{ $draft->id }})` (+88 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 598 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **99 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Process` connect `Process (core model)` to `Process Form`, `ProcessInstance Observer & Tests`, `BPM Scheduler & Advance`, `BPM Design Seeders`, `Checklist Tables`, `Periodic & Escalation Jobs`, `Seeders`, `Mandatory Deadline Watchdog`, `Process Migrations`, `Form Configurators`, `ProcessTrigger Resource`, `Checklist & Company Models`, `Start/Advance Process Actions`, `BPM API Controllers`, `Create/Edit Process`, `ProcessTask & Plan Access`, `RACI Matrix`, `Task Item Answers`?**
  _High betweenness centrality (0.088) - this node is a cross-community bridge._
- **What connects `files`, `App\\`, `Database\\Factories\\` to the rest of the system?**
  _93 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Email Templates` be split into smaller, more focused modules?**
  _Cohesion score 0.08080808080808081 - nodes in this community are weakly interconnected._
- **Why does `ProcessInstance` connect `BPM Scheduler & Advance` to `Clients & Business Functions`, `Function Members`, `ProcessInstance Observer & Tests`, `Checklist & Company Models`, `Chat & ProcessInstance`, `Start/Advance Process Actions`, `ProcessTask & Plan Access`, `RACI Matrix`, `Task Item Answers`, `Process (core model)`, `ProcessTaskItem & Doc Classifier`, `Incoming Emails & Reminders`, `Fornitori/Clienti/Employees`, `Checklist Answers Model`, `Periodic & Escalation Jobs`, `External Check & Chat`, `ProcessInstance Resource`, `Dashboard Widgets`, `Mandatory Deadline Watchdog`?**
  _High betweenness centrality (0.067) - this node is a cross-community bridge._
- **Should `List Pages` be split into smaller, more focused modules?**
  _Cohesion score 0.09879032258064516 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `ProcessInstance Observer & Tests` to `Email Templates`, `Manual Assistant Agent`, `Database Seeder & AI Smoke Test`, `BPM Scheduler & Advance`, `Mandatory Deadline Watchdog`, `External Records Commands`, `SegretariaAiDraftConfirmationTest`, `SsoTokenApiControllerTest`, `Search Past Conversations Tool`, `Start/Advance Process Actions`, `SSO Token Broker`, `Document Classifier Tests`, `Chat Message & Search Tool`, `Task Item Answers`, `Process (core model)`?**
  _High betweenness centrality (0.064) - this node is a cross-community bridge._
- **Should `Create Pages` be split into smaller, more focused modules?**
  _Cohesion score 0.0896551724137931 - nodes in this community are weakly interconnected._