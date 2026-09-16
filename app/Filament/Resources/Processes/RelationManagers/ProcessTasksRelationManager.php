<?php

namespace App\Filament\Resources\Processes\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProcessTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Task del Processo';

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione Task')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nome')->required(),
                    TextInput::make('code')->label('Codice')->nullable(),
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
                        ->preload()
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label('Descrizione')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Matrice RACI')
                ->collapsible()
                ->collapsed()
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
                                    'R' => 'R — Responsible',
                                    'A' => 'A — Accountable',
                                    'C' => 'C — Consulted',
                                    'I' => 'I — Informed',
                                ])
                                ->required(),
                        ]),
                ]),

            Section::make('Trigger di Attivazione')
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('trigger_field')->label('Campo di Attivazione')->nullable(),
                    TextInput::make('trigger_state')->label('Condizione')->nullable()->placeholder('filled | empty | equals'),
                    TextInput::make('trigger_value')->label('Valore di Confronto')->nullable(),
                ]),

            Section::make('Esclusione Condizionale')
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('exclude_field')->label('Campo di Esclusione')->nullable(),
                    TextInput::make('exclude_state')->label('Condizione')->nullable()->placeholder('filled | empty | equals'),
                    TextInput::make('exclude_value')->label('Valore di Confronto')->nullable(),
                ]),

            Section::make('Solleciti')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('has_reminders')->label('Abilita Solleciti')->live()->inline(false),
                    TextInput::make('reminder_interval_days')
                        ->label('Intervallo (giorni)')
                        ->numeric()->default(3)
                        ->hidden(fn (Get $get) => ! $get('has_reminders')),
                    TextInput::make('max_reminders')
                        ->label('Max Solleciti')
                        ->numeric()->default(5)
                        ->hidden(fn (Get $get) => ! $get('has_reminders')),
                ]),

            Section::make('Regole di Escalation')
                ->collapsible()
                ->collapsed()
                ->schema([
                    KeyValue::make('escalation_rules')
                        ->label('Regole di Escalation')
                        ->nullable()
                        ->keyLabel('Livello')
                        ->valueLabel('Ore massime attesa')
                        ->columnSpanFull(),
                ]),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('ordine')->label('#')->sortable(),
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('code')->label('Codice')->placeholder('—'),
                TextColumn::make('businessFunction.name')->label('Funzione di Business')->placeholder('—'),
                TextColumn::make('raci_r')
                    ->label('R')->badge()->color('info')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'R')
                        ->map(fn ($a) => $a->businessFunction?->name)
                        ->filter()->values()->toArray()
                    )->placeholder('—'),
                TextColumn::make('raci_a')
                    ->label('A')->badge()->color('danger')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'A')
                        ->map(fn ($a) => $a->businessFunction?->name)
                        ->filter()->values()->toArray()
                    )->placeholder('—'),
                IconColumn::make('has_reminders')->label('Solleciti')->boolean(),
            ])
            ->defaultSort('ordine', 'asc')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
