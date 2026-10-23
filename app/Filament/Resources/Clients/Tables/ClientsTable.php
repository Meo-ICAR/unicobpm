<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Utils\ColumnFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nominativo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('tax_code')
                    ->label('CF / P.IVA')
                    ->searchable(),
                TextColumn::make('employee_roles')
                    ->label('Ruoli')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return ColumnFilters::apply($table, except: ['employee_roles']);
    }
}
