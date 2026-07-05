<?php

namespace App\Filament\Resources\ProcessInstances\RelationManagers;

use App\Filament\Resources\ProcessTaskExecutions\ProcessTaskExecutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TaskExecutionsRelationManager extends RelationManager
{
    protected static string $relationship = 'taskExecutions';

    protected static ?string $relatedResource = ProcessTaskExecutionResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('started_at', 'desc') // Mostra l'ultimo task eseguito o in corso in cima
            ->columns([
                // 1. STEP IN ESECUZIONE
                TextColumn::make('processTask.name')
                    ->label('Step / Task Lavorato')
                    ->weight('bold')
                    ->searchable(),

                // 2. RISOLUZIONE POLIMORFICA DELL'OPERATORE
                TextColumn::make('assignee')
                    ->label('Operatore')
                    ->state(fn ($record) => $record->assignee?->name ?? 'In coda di reparto')
                    ->description(fn ($record) => $record->assignee_type ? class_basename($record->assignee_type) : 'Nessuno'),

                // 3. STATO DELL'ESECUZIONE DELLO STEP
                TextColumn::make('execution_status')
                    ->label('Esito Step')
                    ->badge()
                    ->colors([
                        'success' => 'completed',
                        'danger' => 'rejected_and_rewinded',
                        'warning' => 'in_progress', // se gestisci anche stati intermedi di esecuzione
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'completed' => 'Completato',
                        'rejected_and_rewinded' => 'Rifiutato (Rewind)',
                        default => $state,
                    }),

                // 4. TIMELINE E SLA (METRICHE DI TEMPO)
                TextColumn::make('started_at')
                    ->label('Assegnato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('claimed_at')
                    ->label('Preso in carico')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('In attesa...'),

                TextColumn::make('completed_at')
                    ->label('Chiuso il')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('In corso...'),

                TextColumn::make('due_at')
                    ->label('Scadenza SLA')
                    ->dateTime('d/m/Y H:i')
                    ->color(fn ($record) => $record->isOverdue() ? 'danger' : 'gray')
                    ->description(fn ($record) => $record->isOverdue() ? 'TASK SCADUTO' : null),
            ])
            ->filters([
                // Puoi inserire filtri per esito o per operatore se necessario
            ])
            ->headerActions([
                // Ora l'azione di creazione ha il contesto corretto della tabella
                CreateAction::make()
                    ->label('Forza Nuova Esecuzione'),
            ])
            ->actions([
                // Permette di entrare in sola lettura nel dettaglio del log di esecuzione
                ViewAction::make()
                    ->label('Vedi Dettagli'),
            ]);
    }
}
