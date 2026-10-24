<?php

namespace App\Filament\Resources\ProcessTaskItems\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProcessTaskItemForm
{
    /** @var array<string, string> */
    private const RACI_ROLES = ['R' => 'R — chi esegue', 'A' => 'A — responsabile', 'C' => 'C — consultato', 'I' => 'I — informato'];

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
            'external_check' => 'Check Esterno (severity + RACI)',
            'external_command' => 'Comando su un\'altra app',
            'await_email_reply' => 'Attesa Risposta Email',
        ];
    }

    /**
     * Campi di config per 'await_email_reply': il processo aspetta la risposta a un'email e avanza quando arriva (letta da regole e
     * dall'AI); le risposte poco sicure le decide un operatore in «Risposte email».
     *
     * @return array<int, Component>
     */
    public static function awaitEmailReplyFields(): array
    {
        $isAwait = fn (Get $get) => $get('action_type') === 'await_email_reply';

        return [
            TextInput::make('config.question')->label('Cosa si è chiesto')->visible($isAwait)
                ->helperText('Aiuta l\'AI a capire la risposta (es. «Conferma la data della visita del 12/11»). Vuoto = nome dell\'azione.'),
            TextInput::make('config.min_confidence')->label('Confidenza minima per avanzare da solo')->numeric()->minValue(50)->maxValue(100)->default(80)->visible($isAwait),
            Select::make('config.on_refuse')->label('Se rifiutano')->options(['review' => 'Decide un operatore', 'answer' => 'Il rifiuto è la risposta e il processo avanza', 'suspend' => 'Sospendi il processo'])
                ->default('review')->visible($isAwait),
            Select::make('config.on_postpone')->label('Se chiedono un rinvio')->options(['review' => 'Decide un operatore', 'grant' => 'Concedi in automatico (entro i limiti)'])
                ->default('review')->visible($isAwait),
            TextInput::make('config.max_postpone_days')->label('Rinvio massimo (giorni oltre il termine attuale)')->numeric()->minValue(1)->default(30)->visible($isAwait),
            TextInput::make('config.max_postponements')->label('Rinvii massimi per step')->numeric()->minValue(1)->default(2)->visible($isAwait),
            Toggle::make('config.auto')->label('Avanza in automatico')->default(true)->visible($isAwait)
                ->helperText('Se spento, ogni risposta passa da un operatore.'),
        ];
    }

    /**
     * Campi di config per 'external_check', condivisi con il RelationManager nel ProcessTaskResource.
     *
     * @return array<int, Component>
     */
    public static function externalCheckFields(): array
    {
        $isExternalCheck = fn (Get $get) => $get('action_type') === 'external_check';

        return [
            Select::make('config.app')
                ->label('Applicativo da interrogare')
                ->options(fn () => collect((array) config('services.apps'))->map(fn ($app) => $app['label'] ?? null)->filter()->all())
                ->visible($isExternalCheck)
                ->required($isExternalCheck),
            TextInput::make('config.command')
                ->label('Check (comando)')
                ->placeholder('clienti:check-missing-piva')
                ->helperText('L\'applicativo risponde a GET /api/checks/{comando} con valore e severity (ok, regular, warning, alert).')
                ->visible($isExternalCheck)
                ->required($isExternalCheck),
            TextInput::make('config.email_template_code')
                ->label('Codice template email')
                ->helperText('Si usa il template con questo codice e la severity restituita dal check; se la severity manca, quello con severity maggiore. Destinatari: RACI del task (regular: R; warning: R+A; alert: R+A+C).')
                ->visible($isExternalCheck)
                ->required($isExternalCheck),
            Select::make('config.to_raci')
                ->label('Scrivi a (ruoli della RACI)')
                ->multiple()
                ->options(self::RACI_ROLES)
                ->helperText('Vuoto = i ruoli della RACI previsti dalla severity (regular: R; warning: R+A; alert: R+A+C). Con ruoli scelti scrivono sempre quelli.')
                ->visible($isExternalCheck),
            ...self::runOnDayFields(['external_check', 'external_command']),
        ];
    }

    /**
     * Campi di config per 'external_command', condivisi con il RelationManager nel ProcessTaskResource.
     *
     * @return array<int, Component>
     */
    public static function externalCommandFields(): array
    {
        $isCommand = fn (Get $get) => $get('action_type') === 'external_command';

        return [
            Select::make('config.app')
                ->label('Applicativo')
                ->options(fn () => collect((array) config('services.apps'))->map(fn ($app) => $app['label'] ?? null)->filter()->all())
                ->visible($isCommand)
                ->required($isCommand),
            TextInput::make('config.command')
                ->label('Comando')
                ->placeholder('proformas:send-pending')
                ->helperText('Deve essere nella whitelist dell\'applicativo (POST /api/commands/{comando}); parte dalla coda delle chiamate in uscita.')
                ->visible($isCommand)
                ->required($isCommand),
            KeyValue::make('config.options')
                ->label('Opzioni del comando')
                ->keyLabel('Opzione')
                ->valueLabel('Valore')
                ->visible($isCommand),
            Select::make('config.cc_raci')
                ->label('Copia conoscenza (ruoli della RACI)')
                ->multiple()
                ->options(self::RACI_ROLES)
                ->helperText('Le email di chi ha questi ruoli nella RACI del passo diventano l\'opzione cc del comando.')
                ->visible($isCommand),
        ];
    }

    /**
     * @param  list<string>  $types  Tipi di azione a cui si applica.
     * @return array<int, Component>
     */
    public static function runOnDayFields(array $types): array
    {
        return [
            TextInput::make('config.run_on_day')
                ->label('Solo il giorno del mese')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->helperText('Vuoto = a ogni esecuzione. Per le azioni mensili di un processo giornaliero (es. 3 = il 3 di ogni mese).')
                ->visible(fn (Get $get) => in_array($get('action_type'), $types, true)),
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
                    ->columnSpanFull()
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
                        ...self::externalCheckFields(),
                        ...self::externalCommandFields(),
                        ...self::awaitEmailReplyFields(),
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
