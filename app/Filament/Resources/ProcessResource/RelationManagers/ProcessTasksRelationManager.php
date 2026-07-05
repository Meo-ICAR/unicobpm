<?php

namespace App\Filament\Resources\ProcessResource\RelationManagers;

use App\Models\BusinessFunction;
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
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
            Section::make('Matrice RACI dello Step')
                ->description('Associa le funzioni aziendali incaricate (Responsible, Accountable, Consulted, Informed)')
                ->collapsible()
                ->schema([
                    Repeater::make('raciAssignments')
                        ->relationship('raciAssignments')
                        ->label('Assegnazioni')
                        ->addActionLabel('Aggiungi Assegnazione RACI')
                        ->schema([
                            // CAMBIATO: mappiamo business_function_id al posto di business_function_code
                            Select::make('business_function_id')
                                ->label('Funzione di Business')
                                // Recuperiamo l'ID come chiave del select e il Nome come etichetta
                                ->options(BusinessFunction::pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->preload(),

                            // CORRETTO: Cambiato da 'role' a 'raci_role'
                            Select::make('raci_role')
                                ->label('Ruolo RACI')
                                ->options([
                                    'R' => 'R - Responsible (Esegue)',
                                    'A' => 'A - Accountable (Approva)',
                                    'C' => 'C - Consulted (Consultato)',
                                    'I' => 'I - Informed (Informato)',
                                ])
                                ->required(),
                        ])
                        ->columns(2),
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

                // ==========================================
                //   COLONNE RACI CORRETTE CON EAGER LOADING
                // ==========================================

                // 1. RESPONSIBLE (R)
                Tables\Columns\TextColumn::make('raci_r')
                    ->label('R')
                    ->badge()
                    ->color('info')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'R')
                        ->map(fn ($assignment) => $assignment->businessFunction?->name) // Mostra il nome dell'ufficio
                        ->filter()
                        ->toArray()
                    )
                    ->placeholder('—'),

                // 2. ACCOUNTABLE (A)
                Tables\Columns\TextColumn::make('raci_a')
                    ->label('A')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('role', 'A')
                        ->map(fn ($assignment) => $assignment->businessFunction?->name)
                        ->filter()
                        ->toArray()
                    )
                    ->placeholder('—'),

                // 3. CONSULTED (C)
                Tables\Columns\TextColumn::make('raci_c')
                    ->label('C')
                    ->badge()
                    ->color('success')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'C')
                        ->map(fn ($assignment) => $assignment->businessFunction?->name)
                        ->filter()
                        ->toArray()
                    )
                    ->placeholder('—'),

                // 4. INFORMED (I)
                Tables\Columns\TextColumn::make('raci_i')
                    ->label('I')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'I')
                        ->map(fn ($assignment) => $assignment->businessFunction?->name)
                        ->filter()
                        ->toArray()
                    )
                    ->placeholder('—'),

                // ==========================================
                Tables\Columns\IconColumn::make('has_reminders')
                    ->boolean()
                    ->label('Solleciti'),

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
