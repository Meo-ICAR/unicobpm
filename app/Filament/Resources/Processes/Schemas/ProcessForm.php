<?php

namespace App\Filament\Resources\Processes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class ProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione')
                ->columns(3)
                ->schema([
                    TextInput::make('code')
                     //   ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),
                    TextInput::make('name')
                        ->required()
                        ->columnSpan(2),
                    TextInput::make('version')
                        ->numeric()
                        ->required()
                        ->default(1),
                    Toggle::make('is_active')
                        ->default(true)
                        ->inline(false),
                    Textarea::make('description')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Target & Trigger')
                ->columns(3)
                ->collapsible()
                ->schema([
                    Select::make('target_model')
                        ->label('Modello Principale del Processo (Target)')
                        ->placeholder('Seleziona il modello associato a questo workflow')
                        ->options(function () {
                            // Recupera l'array del morphMap centralizzato nel tuo AppServiceProvider
                            $morphMap = Relation::morphMap();

                            // Trasforma le classi in nomi leggibili per l'interfaccia utente
                            return collect($morphMap)->mapWithKeys(function ($className, $alias) {
                                $readableName = Str::afterLast($className, '\\');

                                return [$alias => $readableName];
                            })->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->columnSpanFull()
                        ->hint('Indica quale entità aziendale è il soggetto principale di questo tipo di processo.'),
                    KeyValue::make('trigger_filters')
                        ->nullable()
                        ->keyLabel('Chiave')
                        ->valueLabel('Valore')
                        ->columnSpanFull(),
                    TextInput::make('trigger_field')->nullable(),
                    TextInput::make('trigger_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('trigger_value')->nullable(),
                ]),

            Section::make('Esclusione Condizionale')
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('exclude_field')->nullable(),
                    TextInput::make('exclude_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('exclude_value')->nullable(),
                ]),

            Section::make('Periodicità')
                ->columns(2)
                ->schema([
                    Toggle::make('is_periodic')
                        ->live()
                        ->inline(false),
                    TextInput::make('cron_expression')
                        ->nullable()
                        ->hidden(fn (Get $get) => ! $get('is_periodic'))
                        ->required(fn (Get $get) => (bool) $get('is_periodic'))
                        ->placeholder('0 1 10 * *')
                        ->helperText('Formato cron: minuto ora giorno mese giorno-settimana'),
                ]),

            Section::make('Schedulazione')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('last_activated_at')->disabled()->nullable(),
                    DateTimePicker::make('next_run_at')->disabled()->nullable(),
                ]),

        ]);
    }
}
