<?php

namespace App\Filament\Resources\ProcessTaskItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcessTaskItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('task.name')
                    ->label('Task')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('ordine')
                    ->label('Ordine')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('action_type')
                    ->label('Tipo Azione')
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_required')
                    ->label('Obbligatorio')
                    ->boolean(),
                TextColumn::make('documentType.name')
                    ->label('Tipo Documento')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('checklist.name')
                    ->label('Checklist')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('handler_job')
                    ->label('Job Handler')
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
