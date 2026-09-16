<?php

namespace App\Filament\Resources\ChecklistAnswers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistAnswersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('processInstance.id')
                    ->label('Istanza di Processo')
                    ->searchable(),
                TextColumn::make('checklistItem.display_label')
                    ->label('Voce Checklist')
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('checklistItem', fn ($q) => $q
                            ->where('label', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                    }),
                IconColumn::make('value_boolean')
                    ->label('Valore (Sì/No)')
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
