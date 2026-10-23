<?php

namespace App\Filament\Resources\Resources\Tables;

use App\Models\Resource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ResourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('app_name')
                    ->label('Applicazione')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nome modulo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('group')
                    ->label('Gruppo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('min_plan')
                    ->label('Piano minimo')
                    ->badge()
                    ->sortable(),

            ])
            ->filters([
                SelectFilter::make('app_name')
                    ->label('Applicazione')
                    ->options(fn (): array => Resource::query()->distinct()->orderBy('app_name')->pluck('app_name', 'app_name')->all()),
                SelectFilter::make('group')
                    ->label('Gruppo')
                    ->options(fn (): array => Resource::query()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group', 'group')->all()),
                SelectFilter::make('min_plan')
                    ->label('Piano minimo')
                    ->options([
                        Resource::PLAN_BASE => Resource::PLAN_BASE,
                        Resource::PLAN_MEDIUM => Resource::PLAN_MEDIUM,
                        Resource::PLAN_FULL => Resource::PLAN_FULL,
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
