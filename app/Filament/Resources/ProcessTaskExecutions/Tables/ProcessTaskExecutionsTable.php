<?php

namespace App\Filament\Resources\ProcessTaskExecutions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcessTaskExecutionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('processInstance.id')
                    ->label('Istanza di Processo')
                    ->searchable(),
                TextColumn::make('processTask.name')
                    ->label('Task di Processo')
                    ->searchable(),
                TextColumn::make('assignee_type')
                    ->label('Tipo Assegnatario')
                    ->searchable(),
                TextColumn::make('assignee_id')
                    ->label('ID Assegnatario')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Avviato il')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('claimed_at')
                    ->label('Preso in Carico il')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label('Completato il')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('escalation_level')
                    ->label('Livello di Escalation')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Scadenza')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('execution_status')
                    ->label('Stato Esecuzione')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
