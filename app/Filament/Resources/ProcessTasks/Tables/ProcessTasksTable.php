<?php

namespace App\Filament\Resources\ProcessTasks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProcessTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('process.name')
                    ->label('Processo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ordine')
                    ->label('#')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('businessFunction.name')
                    ->label('Funzione di Business')
                    ->placeholder('—'),
                // Colonne RACI aggregate
                TextColumn::make('raci_r')
                    ->label('R')
                    ->badge()
                    ->color('info')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'R')
                        ->map(fn ($a) => $a->businessFunction?->name)
                        ->filter()->values()->toArray()
                    )
                    ->placeholder('—'),
                TextColumn::make('raci_a')
                    ->label('A')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'A')
                        ->map(fn ($a) => $a->businessFunction?->name)
                        ->filter()->values()->toArray()
                    )
                    ->placeholder('—'),
                TextColumn::make('raci_c')
                    ->label('C')
                    ->badge()
                    ->color('success')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'C')
                        ->map(fn ($a) => $a->businessFunction?->name)
                        ->filter()->values()->toArray()
                    )
                    ->placeholder('—'),
                TextColumn::make('raci_i')
                    ->label('I')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Model $record) => $record->raciAssignments
                        ->where('raci_role', 'I')
                        ->map(fn ($a) => $a->businessFunction?->name)
                        ->filter()->values()->toArray()
                    )
                    ->placeholder('—'),
                IconColumn::make('has_reminders')
                    ->label('Solleciti')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Aggiornato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('process_id')
                    ->label('Processo')
                    ->relationship('process', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('ordine')
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
