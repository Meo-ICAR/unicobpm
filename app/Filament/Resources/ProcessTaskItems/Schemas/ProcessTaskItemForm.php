<?php

namespace App\Filament\Resources\ProcessTaskItems\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProcessTaskItemForm
{
    /**
     * Tipi di azione realmente riconosciuti dal motore BPM (ProcessTaskExecutionObserver,
     * ProcessInstance::currentChecklistItems, ecc.). Unica fonte di verità: usato sia qui
     * sia dal RelationManager annidato in ProcessTaskResource, per evitare che le due
     * interfacce salvino valori di action_type differenti/incompatibili.
     *
     * @return array<string, string>
     */
    public static function actionTypeOptions(): array
    {
        return [
            'document_upload' => 'Caricamento Documento',
            'fill_checklist' => 'Compilazione Checklist',
            'text_input' => 'Testo Libero',
            'automated_email' => 'Email Automatica',
            'validation_rule' => 'Regola di Validazione',
            'system_task' => 'Task di Sistema (Job)',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('process_task_id')
                    ->label('Task')
                    ->relationship('task', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),

                Section::make('Identificazione')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Azione')
                            ->required()
                            ->columnSpan(2),
                        TextInput::make('ordine')
                            ->label('Ordine')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Select::make('action_type')
                            ->label('Tipo di Azione')
                            ->options(self::actionTypeOptions())
                            ->required()
                            ->live()
                            ->columnSpan(2),
                        Toggle::make('is_required')
                            ->label('Obbligatoria')
                            ->default(true)
                            ->inline(false),
                    ]),

                Section::make('Configurazione Azione')
                    ->description('I campi mostrati dipendono dal Tipo di Azione selezionato sopra.')
                    ->columns(2)
                    ->hidden(fn (Get $get) => blank($get('action_type')))
                    ->schema([
                        Select::make('document_type_id')
                            ->label('Tipo Documento Richiesto')
                            ->relationship('documentType', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('action_type') === 'document_upload'),
                        Select::make('checklist_id')
                            ->label('Checklist da Compilare')
                            ->relationship('checklist', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('action_type') === 'fill_checklist')
                            ->required(fn (Get $get) => $get('action_type') === 'fill_checklist')
                            ->helperText('Le domande di questa checklist verranno proposte all\'operatore quando la pratica raggiunge questo task.'),
                        TextInput::make('handler_job')
                            ->label('Job da Eseguire (FQCN)')
                            ->placeholder('App\\Jobs\\MioJob')
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('action_type') === 'system_task')
                            ->helperText('Classe PHP eseguita in automatico quando la pratica raggiunge questo task (anche di un pacchetto/applicativo esterno installato via composer). Deve avere un costruttore (int $processInstanceId, array $config = []).'),
                        KeyValue::make('config')
                            ->label('Configurazione')
                            ->keyLabel('Chiave')
                            ->valueLabel('Valore')
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => in_array($get('action_type'), ['automated_email', 'validation_rule'])),
                    ]),
            ]);
    }
}
