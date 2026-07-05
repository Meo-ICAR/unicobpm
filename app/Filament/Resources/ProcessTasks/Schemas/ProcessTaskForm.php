<?php

namespace App\Filament\Resources\ProcessTasks\Schemas;

use Filament\Forms\Components\KeyValue;
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
                        ->relationship('process', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->required(),
                    TextInput::make('code')
                        ->nullable(),
                    TextInput::make('ordine')
                        ->numeric()
                        ->required()
                        ->default(0),
                    Select::make('business_function_id')
                        ->relationship('businessFunction', 'name')
                        ->nullable()
                        ->searchable()
                        ->preload(),
                    Textarea::make('description')
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

            Section::make('Trigger di Attivazione')
                ->columns(3)
                ->collapsible()
                ->schema([
                    TextInput::make('trigger_field')->nullable(),
                    TextInput::make('trigger_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('trigger_value')->nullable(),
                ]),

            Section::make('Esclusione Condizionale')
                ->columns(3)
                ->collapsible()
                ->schema([
                    TextInput::make('exclude_field')->nullable(),
                    TextInput::make('exclude_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('exclude_value')->nullable(),
                ]),

            Section::make('Solleciti')
                ->columns(2)
                ->collapsible()
                ->schema([
                    Toggle::make('has_reminders')
                        ->live()
                        ->inline(false),
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
                ->collapsible()
                ->schema([
                    KeyValue::make('escalation_rules')
                        ->nullable()
                        ->keyLabel('Livello')
                        ->valueLabel('Ore massime attesa')
                        ->columnSpanFull(),
                ]),

        ]);
    }
}
