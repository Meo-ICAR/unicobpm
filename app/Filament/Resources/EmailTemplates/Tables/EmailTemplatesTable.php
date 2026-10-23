<?php

namespace App\Filament\Resources\EmailTemplates\Tables;

use App\Filament\Resources\EmailTemplates\Schemas\EmailTemplateForm;
use App\Filament\Utils\ColumnFilters;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('code')
                    ->label('Codice')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Oggetto')
                    ->searchable(),
                TextColumn::make('app_identifier')
                    ->label('Applicazione')
                    ->placeholder('Tutte')
                    ->formatStateUsing(fn (?string $state): string => EmailTemplateForm::APPS[strtolower((string) $state)] ?? (string) $state),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->placeholder('Qualsiasi'),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->placeholder('Tutte')
                    ->toggleable(),
                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->sortable()
                    ->label('Attivo'),
                //  ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Stato')
                    ->queries(
                        true: fn ($query) => $query->where('is_active', true),
                        false: fn ($query) => $query->where('is_active', false),
                    )
                    ->placeholder('Tutti')
                    ->trueLabel('Solo Attivi')
                    ->falseLabel('Solo Dimessi')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return ColumnFilters::apply($table);
    }
}
