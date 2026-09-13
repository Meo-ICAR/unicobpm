<?php

namespace App\Filament\Actions;

use App\Models\ChecklistAnswer;
use App\Models\ChecklistItem;
use App\Models\ChecklistSubmission;
use App\Models\Document;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;

/**
 * Interfaccia operatore: presenta le azioni richieste dal task attualmente attivo di una
 * pratica (upload documento, testo libero, compilazione checklist) e, alla conferma, crea
 * tutti i record lungo la gerarchia reale del motore BPM:
 *
 *   ProcessInstance -> ProcessTaskExecution -> ProcessTaskItemAnswer -> ChecklistSubmission -> ChecklistAnswer
 *
 * La creazione di ogni ProcessTaskItemAnswer fa scattare ProcessTaskItemAnswerObserver, che
 * controlla il completamento del task e avanza la pratica automaticamente quando tutte le
 * azioni obbligatorie sono state fornite — questa action non deve quindi chiamare
 * AdvanceProcessAction esplicitamente.
 */
class CompleteCurrentTaskAction
{
    public static function make(): Action
    {
        return Action::make('completeCurrentTask')
            ->label('Completa Task Corrente')
            ->icon('heroicon-o-check-circle')
            ->color('primary')
            ->visible(fn (ProcessInstance $record) => $record->status === 'in_progress'
                && $record->currentTask !== null
                && self::pendingItems($record)->isNotEmpty()
            )
            ->modalHeading(fn (ProcessInstance $record) => "Completa: {$record->currentTask?->name}")
            ->modalSubmitActionLabel('Salva e Avanza')
            ->schema(fn (ProcessInstance $record) => self::buildSchema($record))
            ->action(fn (array $data, ProcessInstance $record) => self::process($data, $record));
    }

    /**
     * Gli item del task corrente non ancora risposti per l'esecuzione attiva, escludendo le
     * azioni automatiche di sistema (gestite dagli observer, mai dall'operatore).
     */
    protected static function pendingItems(ProcessInstance $record): Collection
    {
        $task = $record->currentTask;
        $execution = $record->currentTaskExecution;

        if (! $task) {
            return collect();
        }

        $answeredItemIds = $execution
            ? $execution->itemAnswers()->pluck('process_task_item_id')
            : collect();

        return $task->processTaskItems()
            ->whereIn('action_type', ['document_upload', 'text_input', 'fill_checklist'])
            ->orderBy('ordine')
            ->get()
            ->reject(fn (ProcessTaskItem $item) => $answeredItemIds->contains($item->id));
    }

    /**
     * @return array<int, Component>
     */
    protected static function buildSchema(ProcessInstance $record): array
    {
        return self::pendingItems($record)
            ->map(function (ProcessTaskItem $item) {
                $fieldKey = "answers.{$item->id}";

                return Section::make($item->name)
                    ->columns(1)
                    ->schema(match ($item->action_type) {
                        'document_upload' => [
                            FileUpload::make($fieldKey)
                                ->label($item->documentType?->name ?? 'Documento')
                                ->disk('public')
                                ->directory('documents/pratiche')
                                ->required($item->is_required),
                        ],
                        'text_input' => [
                            Textarea::make($fieldKey)
                                ->label('Risposta')
                                ->required($item->is_required),
                        ],
                        'fill_checklist' => self::checklistFields($item, $fieldKey),
                        default => [],
                    });
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, Component>
     */
    protected static function checklistFields(ProcessTaskItem $item, string $fieldKey): array
    {
        $checklist = $item->checklist;

        if (! $checklist) {
            return [];
        }

        $items = $checklist->items;
        $codeToId = $items->pluck('id', 'item_code');

        return $items->map(function (ChecklistItem $checklistItem) use ($fieldKey, $codeToId) {
            $key = "{$fieldKey}.{$checklistItem->id}";
            $label = $checklistItem->question ?: ($checklistItem->label ?: $checklistItem->name);

            $field = match ($checklistItem->type) {
                'boolean' => Toggle::make($key)->inline(false),
                'number' => TextInput::make($key)->numeric(),
                'date' => DatePicker::make($key),
                'select' => Select::make($key)->options($checklistItem->options ?? []),
                'multiselect' => CheckboxList::make($key)->options($checklistItem->options ?? []),
                default => Textarea::make($key),
            };

            // Su un Toggle, "false" è una risposta valida e completa (es. "No"), non un campo
            // vuoto: applicare required() lo respingerebbe come se non fosse stato risposto.
            $field = $field->label($label);

            if ($checklistItem->type !== 'boolean') {
                $field = $field->required($checklistItem->is_required);
            }

            // Visibilità condizionale: mostra la domanda solo se quella da cui dipende ha la
            // risposta attesa (depends_on_code/depends_on_value, finora mai valutati a runtime).
            if (filled($checklistItem->depends_on_code) && $codeToId->has($checklistItem->depends_on_code)) {
                $parentId = $codeToId->get($checklistItem->depends_on_code);
                $parentKey = str_replace(".{$checklistItem->id}", ".{$parentId}", $key);
                $expectedValue = $checklistItem->depends_on_value;

                $field = $field->visible(fn (Get $get) => (string) $get($parentKey) === (string) $expectedValue);
            }

            return $field;
        })->values()->all();
    }

    protected static function process(array $data, ProcessInstance $record): void
    {
        $execution = $record->currentTaskExecution;

        if (! $execution) {
            return;
        }

        foreach (self::pendingItems($record) as $item) {
            $value = data_get($data, "answers.{$item->id}");

            match ($item->action_type) {
                'document_upload' => self::processDocumentUpload($record, $execution->id, $item, $value),
                'text_input' => self::processTextInput($record, $execution->id, $item, $value),
                'fill_checklist' => self::processChecklist($record, $execution->id, $item, (array) $value),
                default => null,
            };

            // Una risposta di knockout può aver appena respinto la pratica: non processiamo
            // gli item restanti di un task che non esiste più.
            if ($record->fresh()->status === 'rejected') {
                Notification::make()
                    ->title('Pratica respinta')
                    ->body('Una risposta ha attivato una regola di knockout.')
                    ->danger()
                    ->send();

                return;
            }
        }

        Notification::make()
            ->title('Task completato')
            ->success()
            ->send();
    }

    protected static function processDocumentUpload(ProcessInstance $record, int $executionId, ProcessTaskItem $item, mixed $path): void
    {
        if (blank($path)) {
            return;
        }

        $document = Document::create([
            'documentable_type' => $record->subject_type,
            'documentable_id' => $record->subject_id,
            'document_type_id' => $item->document_type_id,
            'document_url' => $path,
            'name' => basename((string) $path),
        ]);

        ProcessTaskItemAnswer::create([
            'process_instance_id' => $record->id,
            'process_task_execution_id' => $executionId,
            'process_task_item_id' => $item->id,
            'document_id' => $document->id,
            'user_id' => auth()->id() ?? 0,
            'completed_at' => now(),
        ]);
    }

    protected static function processTextInput(ProcessInstance $record, int $executionId, ProcessTaskItem $item, mixed $value): void
    {
        if (blank($value)) {
            return;
        }

        ProcessTaskItemAnswer::create([
            'process_instance_id' => $record->id,
            'process_task_execution_id' => $executionId,
            'process_task_item_id' => $item->id,
            'value_text' => $value,
            'user_id' => auth()->id() ?? 0,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $answers  chiave = checklist_item_id
     */
    protected static function processChecklist(ProcessInstance $record, int $executionId, ProcessTaskItem $item, array $answers): void
    {
        if (! $item->checklist_id || empty($answers)) {
            return;
        }

        // Creata subito (senza process_task_item_answer_id, valorizzato dopo): le singole
        // ChecklistAnswer possono innescare un rigetto KO prima ancora che l'azione del task
        // risulti "completata", quindi l'ordine di creazione è: submission -> answers -> (se
        // non respinta) risposta riassuntiva sul task item, che fa avanzare la pratica.
        $submission = ChecklistSubmission::create([
            'checklist_id' => $item->checklist_id,
            'status' => 'completed',
            'submitted_at' => now(),
        ]);

        foreach ($answers as $checklistItemId => $answerValue) {
            if ($answerValue === null || $answerValue === '') {
                continue;
            }

            $checklistItem = ChecklistItem::find($checklistItemId);

            if (! $checklistItem) {
                continue;
            }

            ChecklistAnswer::create([
                'process_instance_id' => $record->id,
                'checklist_submission_id' => $submission->id,
                'checklist_item_id' => $checklistItemId,
                'value_boolean' => $checklistItem->type === 'boolean' ? (bool) $answerValue : null,
                'value_text' => $checklistItem->type !== 'boolean'
                    ? (is_array($answerValue) ? implode(', ', $answerValue) : (string) $answerValue)
                    : null,
            ]);

            if ($record->fresh()->status === 'rejected') {
                // La pratica è stata respinta da una risposta di knockout: la checklist resta
                // "completed" così com'è, ma non generiamo la risposta riassuntiva sul task item.
                return;
            }
        }

        $summaryAnswer = ProcessTaskItemAnswer::create([
            'process_instance_id' => $record->id,
            'process_task_execution_id' => $executionId,
            'process_task_item_id' => $item->id,
            'value_text' => 'Checklist compilata: '.$item->checklist->name,
            'user_id' => auth()->id() ?? 0,
            'completed_at' => now(),
        ]);

        $submission->update(['process_task_item_answer_id' => $summaryAnswer->id]);
    }
}
