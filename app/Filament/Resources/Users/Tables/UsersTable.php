<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Utils\ColumnFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('profile_type')
                    ->label('Tipo utente')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'employee' => 'Dipendente',
                        'client' => 'Consulente',
                        'fornitore' => 'Produttore',
                        default => $state ?: '—',
                    })
                    ->searchable(),
                TextColumn::make('profile.name')
                    ->label('Anagrafica')
                    ->placeholder('—'),
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

        return ColumnFilters::apply($table, except: ['profile.name']);
    }
}
