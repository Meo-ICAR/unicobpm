<?php

namespace App\Filament\Resources\ChecklistItems\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ChecklistItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione')
                ->columns(2)
                ->schema([
                    Select::make('checklist_id')
                        ->relationship('checklist', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                    TextInput::make('item_code')
                        ->nullable()
                        ->unique(ignoreRecord: true)
                        ->placeholder('ES-001'),
                    TextInput::make('ordine')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    TextInput::make('name')
                        ->nullable(),
                    TextInput::make('label')
                        ->nullable(),
                    Textarea::make('question')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Tipo di Risposta')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->options([
                            'boolean'     => 'Sì / No',
                            'text'        => 'Testo libero',
                            'number'      => 'Numero',
                            'date'        => 'Data',
                            'select'      => 'Selezione singola',
                            'multiselect' => 'Selezione multipla',
                        ])
                        ->default('boolean')
                        ->required()
                        ->live(),
                    Toggle::make('is_required')
                        ->label('Risposta obbligatoria')
                        ->default(true)
                        ->inline(false),
                    KeyValue::make('options')
                        ->nullable()
                        ->keyLabel('Chiave')
                        ->valueLabel('Etichetta')
                        ->columnSpanFull()
                        ->hidden(fn (Get $get) => ! in_array($get('type'), ['select', 'multiselect']))
                        ->helperText('Opzioni disponibili per il tipo select/multiselect'),
                ]),

            Section::make('Automazione Stato')
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->description('Se compilati, l\'observer aggiorna l\'anagrafica automaticamente')
                ->schema([
                    TextInput::make('trigger_model')->nullable(),
                    TextInput::make('trigger_field')->nullable(),
                    TextInput::make('trigger_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('trigger_value')->nullable(),
                    Toggle::make('is_timestamp_update')
                        ->label('Aggiorna timestamp')
                        ->helperText('Inserisce Carbon::now() nella colonna target')
                        ->inline(false),
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

            Section::make('Regole di Knockout')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('is_knockout')
                        ->label('Abilita knockout')
                        ->helperText('Se la risposta corrisponde al valore knockout, la pratica passa a "rejected"')
                        ->inline(false)
                        ->live(),
                    TextInput::make('knockout_value')
                        ->nullable()
                        ->hidden(fn (Get $get) => ! $get('is_knockout')),
                ]),

            Section::make('Dipendenze')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->description('Mostra questa voce solo se un\'altra domanda ha un certo valore')
                ->schema([
                    TextInput::make('depends_on_code')
                        ->nullable()
                        ->placeholder('item_code della domanda padre'),
                    TextInput::make('depends_on_value')
                        ->nullable()
                        ->placeholder('Valore atteso'),
                ]),

        ]);
    }
}
