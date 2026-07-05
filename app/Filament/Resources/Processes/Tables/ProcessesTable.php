<?php

namespace App\Filament\Resources\Processes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProcessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Attivo'),
                IconColumn::make('is_periodic')
                    ->boolean()
                    ->label('Periodico'),
                TextColumn::make('next_run_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Prossima Esecuzione')
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Stato')
                    ->trueLabel('Solo attivi')
                    ->falseLabel('Solo disattivati'),
                TernaryFilter::make('is_periodic')
                    ->label('Periodicità')
                    ->trueLabel('Solo periodici'),
            ])
            ->defaultSort('code')
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
