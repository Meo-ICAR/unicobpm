<?php

namespace App\Filament\Resources\ChecklistItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('checklist.name')
                    ->searchable(),
                TextColumn::make('item_code')
                    ->searchable(),
                TextColumn::make('ordine')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                IconColumn::make('is_required')
                    ->boolean(),
                TextColumn::make('trigger_model')
                    ->searchable(),
                TextColumn::make('trigger_field')
                    ->searchable(),
                TextColumn::make('trigger_state')
                    ->searchable(),
                TextColumn::make('trigger_value')
                    ->searchable(),
                TextColumn::make('exclude_field')
                    ->searchable(),
                TextColumn::make('exclude_state')
                    ->searchable(),
                TextColumn::make('exclude_value')
                    ->searchable(),
                IconColumn::make('is_timestamp_update')
                    ->boolean(),
                IconColumn::make('is_knockout')
                    ->boolean(),
                TextColumn::make('knockout_value')
                    ->searchable(),
                TextColumn::make('depends_on_code')
                    ->searchable(),
                TextColumn::make('depends_on_value')
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
