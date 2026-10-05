<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\Checklist;
use App\Models\DocumentType;
use App\Models\Process;
use App\Models\ProcessTask;
use Illuminate\Database\Seeder;

/**
 * Popola i processi (e relativi task/RACI/azioni) tipici dell'attività quotidiana
 * di un mediatore creditizio, oltre al processo "Onboarding Nuovo Agente" già
 * fornito da BpmDesignSeeder:
 *
 * - PRC-AML: adeguata verifica e monitoraggio antiriciclaggio del cliente.
 * - PRC-TRANSP: trasparenza precontrattuale bancaria/assicurativa.
 * - PRC-OAM: mantenimento dei requisiti di iscrizione OAM del mediatore/collaboratore.
 * - PRC-ISTRUTTORIA: istruttoria della pratica di finanziamento, dalla raccolta
 *   documenti all'esito della delibera dell'istituto convenzionato.
 *
 * Richiede ProcessSeeder, BusinessFunctionSeeder e DocumentTypeSeeder già eseguiti.
 * Scritto per essere idempotente: rieseguirlo non duplica task/RACI/azioni.
 */
class CreditBrokerProcessesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAmlProcess();
        $this->seedTransparencyProcess();
        $this->seedOamProcess();
        $this->seedLoanApplicationProcess();
    }

    /**
     * NOTA: i primi due task di PRC-AML (ordine 10 e 20) sono già seminati da
     * ProcessTaskSeeder + ProcessTaskRaciSeeder con una matrice RACI a 4 ruoli completa.
     * Qui aggiungiamo solo il terzo step (monitoraggio), senza toccare ordine 10/20.
     */
    private function seedAmlProcess(): void
    {
        $process = Process::where('code', 'PRC-AML')->first();
        if (! $process) {
            $this->command?->warn('CreditBrokerProcessesSeeder: processo PRC-AML non trovato, skip.');

            return;
        }

        $ctrlCompl = BusinessFunction::where('code', 'CTRL-COMPL')->first();

        $task3 = $this->upsertTask($process, 30, 'Monitoraggio Continuativo', $ctrlCompl);
        $this->upsertItem($task3, 1, 'Esito monitoraggio periodico', 'text_input', null, true);
    }

    private function seedTransparencyProcess(): void
    {
        $process = Process::where('code', 'PRC-TRANSP')->first();
        if (! $process) {
            $this->command?->warn('CreditBrokerProcessesSeeder: processo PRC-TRANSP non trovato, skip.');

            return;
        }

        $reteEsterna = BusinessFunction::where('code', 'BUS-RETE-EXT')->first();
        $ctrlCompl = BusinessFunction::where('code', 'CTRL-COMPL')->first();
        $busBo = BusinessFunction::where('code', 'BUS-BO')->first();
        $contratto = DocumentType::where('code', 'CONTRATTO_FIRMATO')->first();

        $task1 = $this->upsertTask($process, 10, 'Consegna Documentazione Precontrattuale', $reteEsterna);
        $this->upsertItem($task1, 1, 'Consegna Foglio Informativo al Cliente', 'text_input', null, true);

        $checklist = Checklist::where('code', 'chk_trasparenza_01')->first();

        $task2 = $this->upsertTask($process, 20, 'Verifica Comprensione Cliente', $ctrlCompl);
        $this->upsertItem($task2, 1, 'Questionario di Comprensione del Prodotto', 'fill_checklist', null, true, null, $checklist?->id);

        $task3 = $this->upsertTask($process, 30, 'Archiviazione Fascicolo', $busBo);
        $this->upsertItem($task3, 1, 'Caricamento Contratto Firmato', 'document_upload', $contratto?->id, true);
    }

    private function seedOamProcess(): void
    {
        $process = Process::where('code', 'PRC-OAM')->first();
        if (! $process) {
            $this->command?->warn('CreditBrokerProcessesSeeder: processo PRC-OAM non trovato, skip.');

            return;
        }

        $ctrlCompl = BusinessFunction::where('code', 'CTRL-COMPL')->first();
        $busBo = BusinessFunction::where('code', 'BUS-BO')->first();
        $hr = BusinessFunction::where('code', 'HR')->first();
        $polizza = DocumentType::where('code', 'POLIZZA_RC_PROFESSIONALE')->first();
        $attestato = DocumentType::where('code', 'ATTESTATO_FORMAZIONE')->first();

        $checklist = Checklist::where('code', 'chk_oam_01')->first();

        $task1 = $this->upsertTask($process, 10, 'Verifica Requisiti Onorabilità e Professionalità', $ctrlCompl);
        $this->upsertItem($task1, 1, 'Questionario Requisiti OAM', 'fill_checklist', null, true, null, $checklist?->id);

        $task2 = $this->upsertTask($process, 20, 'Rinnovo Assicurazione RC Professionale', $busBo);
        $this->upsertItem($task2, 1, 'Caricamento Polizza RC Professionale', 'document_upload', $polizza?->id, true);

        $task3 = $this->upsertTask($process, 30, 'Formazione Continua Obbligatoria', $hr);
        $this->upsertItem($task3, 1, 'Caricamento Attestato di Formazione', 'document_upload', $attestato?->id, true);
    }

    private function seedLoanApplicationProcess(): void
    {
        $process = Process::updateOrCreate(
            ['code' => 'PRC-ISTRUTTORIA'],
            [
                'name' => 'Istruttoria Pratica di Finanziamento',
                'description' => 'Dalla raccolta della documentazione reddituale del cliente alla trasmissione della pratica '.
                    'all\'istituto convenzionato, fino alla gestione dell\'esito della delibera.',
                'is_active' => true,
            ]
        );

        $reteEsterna = BusinessFunction::where('code', 'BUS-RETE-EXT')->first();
        $busBo = BusinessFunction::where('code', 'BUS-BO')->first();
        $bustaPaga = DocumentType::where('code', 'BUSTA_PAGA')->first();
        $estrattoConto = DocumentType::where('code', 'ESTRATTO_CONTO')->first();
        $delibera = DocumentType::where('code', 'DELIBERA_ISTITUTO')->first();

        $task1 = $this->upsertTask($process, 10, 'Raccolta Documentazione Reddituale', $reteEsterna, [
            'has_reminders' => true,
            'reminder_interval_days' => 3,
            'max_reminders' => 2,
        ]);
        $this->upsertItem($task1, 1, 'Caricamento Busta Paga / CU', 'document_upload', $bustaPaga?->id, true);
        $this->upsertItem($task1, 2, 'Caricamento Estratto Conto Bancario', 'document_upload', $estrattoConto?->id, true);

        $task2 = $this->upsertTask($process, 20, 'Invio Pratica all\'Istituto Convenzionato', $busBo);
        $this->upsertItem($task2, 1, 'Conferma Trasmissione Pratica', 'text_input', null, true);
        $this->upsertItem($task2, 2, 'Verifica Blacklist Agente', 'blacklist_check', null, true, [
            'pratica_id_field' => 'subject_id',
            'app' => 'unicoloan',
        ]);

        // Task in attesa dell'istituto finanziatore: dotato di regole di escalation per sollecitare
        // se la delibera tarda ad arrivare (usato da TaskEscalationWatchdogJob).
        $task3 = $this->upsertTask($process, 30, 'Gestione Esito Delibera', $busBo, [
            'escalation_rules' => [
                ['level' => 1, 'delay_hours' => 72, 'action' => 'notify_assignee'],
                ['level' => 2, 'delay_hours' => 168, 'action' => 'notify_manager', 'manager_email' => 'responsabile.crediti@mediatore.it'],
            ],
        ]);
        $this->upsertItem($task3, 1, 'Caricamento Delibera Istituto', 'document_upload', $delibera?->id, true);
    }

    /**
     * Crea o aggiorna un ProcessTask individuato da (process_id, ordine), assegnandogli
     * opzionalmente un responsabile RACI 'R' sulla business function indicata.
     *
     * @param  array<string, mixed>  $extra
     */
    private function upsertTask(Process $process, int $ordine, string $name, ?BusinessFunction $responsible, array $extra = []): ProcessTask
    {
        $task = $process->tasks()->updateOrCreate(
            ['process_id' => $process->id, 'ordine' => $ordine],
            ['name' => $name] + $extra
        );

        if ($responsible) {
            $task->raciAssignments()->updateOrCreate(
                ['business_function_id' => $responsible->id],
                ['raci_role' => 'R']
            );
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    private function upsertItem(ProcessTask $task, int $ordine, string $name, string $actionType, ?int $documentTypeId, bool $isRequired, ?array $config = null, ?int $checklistId = null): void
    {
        $task->processTaskItems()->updateOrCreate(
            ['process_task_id' => $task->id, 'ordine' => $ordine],
            [
                'name' => $name,
                'action_type' => $actionType,
                'document_type_id' => $documentTypeId,
                'checklist_id' => $checklistId,
                'is_required' => $isRequired,
                'config' => $config,
            ]
        );
    }
}
