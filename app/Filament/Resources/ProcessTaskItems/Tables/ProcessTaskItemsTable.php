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
                    ->searchable(),
                TextColumn::make('ordine')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('action_type')
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_required')
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
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
