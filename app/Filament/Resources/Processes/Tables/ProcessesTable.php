<?php

namespace App\Filament\Resources\Processes\Tables;

use App\Models\Process;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
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
                    ->label('Codice')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('Task')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
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
                Action::make('raciMatrix')
                    ->label('RACI')
                    ->icon('heroicon-o-table-cells')
                    ->color('gray')
                    ->modalHeading(fn (Process $record) => "Task e Matrice RACI — {$record->name}")
                    ->modalContent(fn (Process $record) => view('filament.processes.raci-matrix', ['process' => $record]))
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),
                EditAction::make(),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
