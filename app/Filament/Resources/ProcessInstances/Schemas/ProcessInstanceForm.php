<?php

namespace App\Filament\Resources\ProcessInstances\Schemas;

use App\Models\Consultant;
use App\Models\Employee;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProcessInstanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3) // Layout a 3 colonne per separare dati macro e sidebar di telemetria
                    ->schema([

                        // COLONNA LATERALE SINISTRA: Informazioni e Soggetto della Pratica
                        Grid::make(1)
                            ->schema([
                                Section::make('Informazioni Processo')
                                    ->description('Configurazione e step attivo del workflow.')
                                    ->compact()
                                    ->schema([
                                        Select::make('process_id')
                                            ->relationship('process', 'name')
                                            ->label('Processo Macro')
                                            ->required()
                                            ->disabledOn('edit') // Blindato in modifica per proteggere il flusso operativo
                                            ->preload(),

                                        Select::make('current_task_id')
                                            ->relationship('currentTask', 'name')
                                            ->label('Step Attivo')
                                            ->placeholder('Nessuno step attivo'),
                                    ]),

                                Section::make('Anagrafica della Pratica')
                                    ->description('Associazione polimorfica del soggetto in lavorazione.')
                                    ->compact()
                                    ->schema([
                                        // Risolve subject_type e subject_id in una select unica e intelligente
                                        MorphToSelect::make('subject')
                                            ->label('Oggetto Pratica')
                                            ->types([
                                                MorphToSelect\Type::make(Employee::class)
                                                    ->label('Dipendente')
                                                    ->titleAttribute('name'),
                                                MorphToSelect\Type::make(Consultant::class)
                                                    ->label('Consulente')
                                                    ->titleAttribute('name'),
                                            ])
                                            ->required()
                                            ->searchable()
                                            ->preload(),
                                    ]),
                            ])
                            ->columnSpan(2),

                        // COLONNA LATERALE DESTRA: Stato attuale, Assegnazione e Monitoraggio SLA
                        Grid::make(1)
                            ->schema([
                                Section::make('Stato & Assegnazione')
                                    ->compact()
                                    ->schema([
                                        Select::make('status')
                                            ->label('Stato Globale')
                                            ->options([
                                                'pending' => 'In Attesa',
                                                'in_progress' => 'In Lavorazione',
                                                'completed' => 'Completato',
                                                'rejected' => 'Rifiutato',
                                                'cancelled' => 'Annullato',
                                            ])
                                            ->default('pending')
                                            ->required(),

                                        // Risolve current_assignee_type e current_assignee_id per l'operatore
                                        MorphToSelect::make('currentAssignee')
                                            ->label('In Carico A')
                                            ->types([
                                                MorphToSelect\Type::make(Employee::class)
                                                    ->label('Dipendente Operatore')
                                                    ->titleAttribute('name'),
                                                MorphToSelect\Type::make(Consultant::class)
                                                    ->label('Consulente Operatore')
                                                    ->titleAttribute('name'),
                                            ])
                                            ->searchable()
                                            ->placeholder('Disponibile in coda')
                                            ->preload(),
                                    ]),

                                Section::make('Telemetria & Solleciti')
                                    ->compact()
                                    ->schema([
                                        TextInput::make('company_id')
                                            ->label('ID Agenzia/Hub')
                                            ->disabled()
                                            ->placeholder('Assegnato automaticamente'),

                                        TextInput::make('reminders_sent_count')
                                            ->label('Solleciti Inviati')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->disabled() // In sola lettura per l'utente, aggiornato dal motore di backend
                                            ->prefixIcon('heroicon-m-bell'),

                                        DateTimePicker::make('last_reminder_sent_at')
                                            ->label('Ultimo Sollecito il')
                                            ->disabled(),

                                        DateTimePicker::make('completed_at')
                                            ->label('Data Chiusura')
                                            ->disabled()
                                            // Mostra il timestamp di chiusura solo se la pratica è in uno stato conclusivo
                                            ->visible(fn ($get) => in_array($get('status'), ['completed', 'rejected', 'cancelled'])),
                                    ]),
                            ])
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
