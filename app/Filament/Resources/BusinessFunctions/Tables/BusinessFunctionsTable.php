<?php

namespace App\Filament\Resources\BusinessFunctions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BusinessFunctionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Codice')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('macro_area')
                    ->label('Macro area')
                    ->badge(),
                TextColumn::make('type')
                    ->label('Tipologia')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Strategica' => 'danger',
                        'Operativa' => 'success',
                        'Controllo' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('outsourcable_status')
                    ->label('Esternalizzabile')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'yes' => 'Sì',
                        'no' => 'No',
                        'partial' => 'Parziale',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'yes' => 'success',
                        'partial' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('managed_by_code')
                    ->label('Gestita dal codice')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Email di contatto')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('macro_area')
                    ->label('Macro area')
                    ->options([
                        'Governance' => 'Governance',
                        'Business / Commerciale' => 'Business / Commerciale',
                        'Supporto' => 'Supporto',
                        'Controlli (II Livello)' => 'Controlli (II Livello)',
                        'Controlli (III Livello)' => 'Controlli (III Livello)',
                        'Controlli / Privacy' => 'Controlli / Privacy',
                    ]),
                SelectFilter::make('type')
                    ->label('Tipologia')
                    ->options([
                        'Strategica' => 'Strategica',
                        'Operativa' => 'Operativa',
                        'Supporto' => 'Supporto',
                        'Controllo' => 'Controllo',
                    ]),
                SelectFilter::make('outsourcable_status')
                    ->label('Esternalizzabile')
                    ->options([
                        'yes' => 'Sì',
                        'no' => 'No',
                        'partial' => 'Parziale',
                    ]),
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
