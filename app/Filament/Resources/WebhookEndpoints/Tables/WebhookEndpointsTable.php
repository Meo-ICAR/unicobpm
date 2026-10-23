<?php

namespace App\Filament\Resources\WebhookEndpoints\Tables;

use App\Filament\Utils\ColumnFilters;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class WebhookEndpointsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('url')->label('Indirizzo')->limit(60),
                TextColumn::make('events')->label('Eventi')->badge()->placeholder('Tutti'),
                ToggleColumn::make('is_active')->label('Attivo'),
            ])
            ->recordActions([EditAction::make()]);

        return ColumnFilters::apply($table, except: ['events']);
    }
}
