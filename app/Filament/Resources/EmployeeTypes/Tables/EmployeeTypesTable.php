<?php

namespace App\Filament\Resources\EmployeeTypes\Tables;

use App\Filament\Utils\ColumnFilters;
use App\Models\EmployeeType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeeTypesTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Codice ufficio')
                    ->searchable(),
                TextColumn::make('macro_area')
                    ->label('Macro area')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('outsourcable_status')
                    ->label('Esternalizzabile')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EmployeeType::OUTSOURCABLE[$state] ?? (string) $state),
                TextColumn::make('email')
                    ->label('Email ufficio')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('icon')
                    ->label('Icona')
                    ->searchable(),
                TextColumn::make('companytype')
                    ->label('Tipo Azienda')
                    ->searchable(),
                IconColumn::make('is_external')
                    ->label('Ruolo Esternalizzabile')
                    ->boolean(),
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
                ViewAction::make(),
            ]);

        return ColumnFilters::apply($table);
    }
}
