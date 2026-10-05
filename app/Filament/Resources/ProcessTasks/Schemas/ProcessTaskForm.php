<?php

namespace App\Filament\Resources\ProcessTasks\Schemas;

use App\Models\BusinessFunction;
use App\Models\Process;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProcessTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione')
                ->columns(2)
                ->schema([
                    Select::make('process_id')
                        ->label('Processo')
                        ->relationship('process', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->label('Nome')
                        ->required(),
                    TextInput::make('code')
                        ->label('Codice')
                        ->nullable(),
                    TextInput::make('ordine')
                        ->label('Ordine')
                        ->numeric()
                        ->required()
                        ->default(0),
                    Select::make('business_function_id')
                        ->label('Funzione Aziendale')
                        ->relationship('businessFunction', 'name')
                        ->nullable()
                        ->searchable()
                        ->preload(),
                    Textarea::make('description')
                        ->label('Descrizione')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Matrice RACI')
                ->description('Funzioni aziendali coinvolte: Responsible, Accountable, Consulted, Informed')
                ->collapsible()
                ->schema([
                    Repeater::make('raciAssignments')
                        ->relationship('raciAssignments')
                        ->label('Assegnazioni RACI')
                        ->addActionLabel('Aggiungi riga RACI')
                        ->columns(2)
                        ->schema([
                            Select::make('business_function_id')
                                ->label('Funzione di Business')
                                ->relationship('businessFunction', 'name')
                                ->required()
                                ->searchable()
                                ->preload(),
                            Select::make('raci_role')
                                ->label('Ruolo RACI')
                                ->options([
                                    'R' => 'R — Responsible (Esegue)',
                                    'A' => 'A — Accountable (Approva)',
                                    'C' => 'C — Consulted (Consultato)',
                                    'I' => 'I — Informed (Informato)',
                                ])
                                ->required(),
                        ]),
                ]),

            Section::make('Salta Task se...')
                ->description('Condizioni per cui questo task non serve per un determinato record e va saltato automaticamente durante l\'avanzamento della pratica.')
                ->columns(3)
                ->collapsible()
                ->schema([
                    Select::make('trigger_field')
                        ->label('Esegui il task solo se il campo')
                        ->options(fn (Get $get) => Process::targetModelColumnOptions(Process::find($get('process_id'))?->target_model))
                        ->searchable()
                        ->nullable()
                        ->disabled(fn (Get $get) => blank($get('process_id')))
                        ->placeholder('Sempre (nessuna condizione)')
                        ->helperText('Il task viene proposto solo se il record soddisfa questa condizione.'),
                    Select::make('trigger_state')
                        ->label('Condizione')
                        ->options([
                            'filled' => 'È valorizzato',
                            'empty' => 'È vuoto',
                            'equals' => 'È uguale a...',
                        ])
                        ->nullable()
                        ->live(),
                    TextInput::make('trigger_value')
                        ->label('Valore di Confronto')
                        ->nullable()
                        ->hidden(fn (Get $get) => $get('trigger_state') !== 'equals'),

                    Select::make('exclude_field')
                        ->label('Salta il task se il campo')
                        ->options(fn (Get $get) => Process::targetModelColumnOptions(Process::find($get('process_id'))?->target_model))
                        ->searchable()
                        ->nullable()
                        ->disabled(fn (Get $get) => blank($get('process_id')))
                        ->placeholder('Nessuna esclusione')
                        ->helperText('Se il record soddisfa questa condizione, il task viene saltato anche se il campo sopra è soddisfatto.'),
                    Select::make('exclude_state')
                        ->label('Condizione')
                        ->options([
                            'filled' => 'È valorizzato',
                            'empty' => 'È vuoto',
                            'equals' => 'È uguale a...',
                        ])
                        ->nullable()
                        ->live(),
                    TextInput::make('exclude_value')
                        ->label('Valore di Confronto')
                        ->nullable()
                        ->hidden(fn (Get $get) => $get('exclude_state') !== 'equals'),
                ]),

            Section::make('Solleciti')
                ->description('Promemoria automatici se la pratica resta ferma su questo task.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    Toggle::make('has_reminders')
                        ->label('Abilita Solleciti')
                        ->live()
                        ->inline(false)
                        ->columnSpanFull(),
                    TextInput::make('reminder_interval_days')
                        ->label('Intervallo (giorni)')
                        ->numeric()
                        ->default(3)
                        ->hidden(fn (Get $get) => ! $get('has_reminders')),
                    TextInput::make('max_reminders')
                        ->label('Max solleciti')
                        ->numeric()
                        ->default(5)
                        ->hidden(fn (Get $get) => ! $get('has_reminders')),
                ]),

            Section::make('Regole di Escalation')
                ->description('Se il task resta pendente oltre le ore indicate, scatta l\'azione configurata (controllato ogni ora da TaskEscalationWatchdogJob).')
                ->collapsible()
                ->schema([
                    Repeater::make('escalation_rules')
                        ->label('Livelli di Escalation')
                        ->addActionLabel('Aggiungi livello')
                        ->reorderable(false)
                        ->columns(3)
                        ->schema([
                            TextInput::make('level')
                                ->label('Livello')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->helperText('Progressivo: 1, 2, 3...'),
                            TextInput::make('delay_hours')
                                ->label('Dopo quante ore')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->suffix('ore'),
                            Select::make('action')
                                ->label('Azione')
                                ->options([
                                    'notify_assignee' => 'Avvisa Assegnatario',
                                    'notify_manager' => 'Avvisa Responsabile',
                                    'reassign' => 'Riassegna ad altro Ufficio',
                                ])
                                ->required()
                                ->live(),
                            TextInput::make('manager_email')
                                ->label('Email Responsabile')
                                ->email()
                                ->columnSpanFull()
                                ->visible(fn (Get $get) => $get('action') === 'notify_manager'),
                            Select::make('target_business_function')
                                ->label('Nuovo Ufficio Assegnatario')
                                ->options(fn () => BusinessFunction::pluck('name', 'name'))
                                ->searchable()
                                ->columnSpanFull()
                                ->visible(fn (Get $get) => $get('action') === 'reassign')
                                ->helperText('La riassegnazione automatica non è ancora implementata: oggi viene solo registrata nel log di audit.'),
                        ]),
                ]),

        ]);
    }
}
