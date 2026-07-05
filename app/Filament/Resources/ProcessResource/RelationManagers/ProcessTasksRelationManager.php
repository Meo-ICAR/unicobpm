<?php

namespace App\Filament\Resources\ProcessResource\RelationManagers;

use App\Models\BusinessFunction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Task del Processo';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([

            Section::make('Identificazione Task')
                ->columns(2)
                ->schema([
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
                        ->preload()
                        ->columnSpanFull(),
                ]),
            // ==========================================
            //       NUOVA SEZIONE: ASSEGNAZIONE RACI
            // ==========================================
            Section::make('Matrice RACI dello Step')
                ->description('Associa le funzioni aziendali incaricate (Responsible, Accountable, Consulted, Informed)')
                ->collapsible()
                ->schema([
                    Repeater::make('raciAssignments')
                        ->relationship('raciAssignments') // Relazione HasMany verso process_task_raci
                        ->grid(2) // Dispone le assegnazioni su due colonne per risparmiare spazio verticale
                        ->label('Assegnazioni')
                        ->addActionLabel('Aggiungi Assegnazione RACI')
                        ->schema([
                            Select::make('business_function_code') // o business_function_id a seconda del tuo DB
                                ->label('Funzione di Business')
                                ->options(BusinessFunction::pluck('name', 'code'))
                                ->required()
                                ->searchable()
                                ->preload(),

                            Select::make('role')
                                ->label('Ruolo RACI')
                                ->options([
                                    'R' => 'R - Responsible (Esegue)',
                                    'A' => 'A - Accountable (Approva)',
                                    'C' => 'C - Consulted (Consultato)',
                                    'I' => 'I - Informed (Informato)',
                                ])
                                ->required(),
                        ]),
                ]),
            // ==========================================
            Textarea::make('description')
                ->nullable()
                ->columnSpanFull(),

            Section::make('Trigger di Attivazione')
                ->columns(3)
                ->schema([
                    TextInput::make('trigger_field')->nullable(),
                    TextInput::make('trigger_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('trigger_value')->nullable(),
                ]),

            Section::make('Esclusione Condizionale')
                ->columns(3)
                ->schema([
                    TextInput::make('exclude_field')->nullable(),
                    TextInput::make('exclude_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('exclude_value')->nullable(),
                ]),

            Section::make('Solleciti')
                ->columns(2)
                ->schema([
                    Toggle::make('has_reminders')
                        ->live()
                        ->inline(false),
                    TextInput::make('reminder_interval_days')
                        ->numeric()
                        ->default(3)
                        ->hidden(fn (Get $get) => ! $get('has_reminders')),
                    TextInput::make('max_reminders')
                        ->numeric()
                        ->default(5)
                        ->hidden(fn (Get $get) => ! $get('has_reminders')),
                ]),

            Section::make('Regole di Escalation')
                ->schema([
                    KeyValue::make('escalation_rules')
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
                Tables\Columns\TextColumn::make('ordine')
                    ->sortable()
                    ->label('#'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nome'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Codice')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('businessFunction.name')
                    ->label('Funzione di Business')
                    ->placeholder('—'),
                // ==========================================
                //       NUOVE COLONNE PER VISUALIZZARE RACI
                // ==========================================

                // 1. COLONNA: RESPONSIBLE (R)
                Tables\Columns\TextColumn::make('raci_r')
                    ->label('R (Responsible)')
                    ->badge()
                    ->color('info')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('role', 'R')
                        ->map(fn ($assignment) => $assignment->business_function_code ?? $assignment->business_function_id)
                        ->toArray()
                    )
                    ->placeholder('—'),

                // 2. COLONNA: ACCOUNTABLE (A)
                Tables\Columns\TextColumn::make('raci_a')
                    ->label('A (Accountable)')
                    ->badge()
                    ->color('danger') // Rosso per evidenziare chi approva ed è l'ultimo responsabile
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('role', 'A')
                        ->map(fn ($assignment) => $assignment->business_function_code ?? $assignment->business_function_id)
                        ->toArray()
                    )
                    ->placeholder('—'),

                // 3. COLONNA: CONSULTED (C)
                Tables\Columns\TextColumn::make('raci_c')
                    ->label('C (Consulted)')
                    ->badge()
                    ->color('success')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('role', 'C')
                        ->map(fn ($assignment) => $assignment->business_function_code ?? $assignment->business_function_id)
                        ->toArray()
                    )
                    ->placeholder('—'),

                // 4. COLONNA: INFORMED (I)
                Tables\Columns\TextColumn::make('raci_i')
                    ->label('I (Informed)')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('role', 'I')
                        ->map(fn ($assignment) => $assignment->business_function_code ?? $assignment->business_function_id)
                        ->toArray()
                    )
                    ->placeholder('—'),

                // ==========================================
                Tables\Columns\IconColumn::make('has_reminders')
                    ->boolean()
                    ->label('Solleciti'),
                Tables\Columns\TextColumn::make('reminder_interval_days')
                    ->label('Intervallo (gg)')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('max_reminders')
                    ->label('Max Solleciti')
                    ->placeholder('—'),
            ])
            ->defaultSort('ordine', 'asc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
