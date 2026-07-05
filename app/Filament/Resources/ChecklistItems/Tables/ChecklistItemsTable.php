<?php

namespace App\Filament\Resources\ChecklistItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ChecklistItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('checklist.name')
                    ->label('Checklist')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ordine')
                    ->label('#')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('item_code')
                    ->label('Codice')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('label')
                    ->label('Voce')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'boolean'     => 'gray',
                        'text'        => 'info',
                        'number'      => 'warning',
                        'date'        => 'success',
                        'select'      => 'primary',
                        'multiselect' => 'danger',
                        default       => 'gray',
                    }),
                IconColumn::make('is_required')
                    ->label('Obbl.')
                    ->boolean(),
                IconColumn::make('is_knockout')
                    ->label('KO')
                    ->boolean(),
                TextColumn::make('knockout_value')
                    ->label('Valore KO')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('depends_on_code')
                    ->label('Dipende da')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Aggiornato')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordine')
            ->filters([
                SelectFilter::make('checklist_id')
                    ->label('Checklist')
                    ->relationship('checklist', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'boolean'     => 'Sì / No',
                        'text'        => 'Testo libero',
                        'number'      => 'Numero',
                        'date'        => 'Data',
                        'select'      => 'Selezione singola',
                        'multiselect' => 'Selezione multipla',
                    ]),
                TernaryFilter::make('is_knockout')
                    ->label('Solo knockout'),
                TernaryFilter::make('is_required')
                    ->label('Obbligatori'),
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
