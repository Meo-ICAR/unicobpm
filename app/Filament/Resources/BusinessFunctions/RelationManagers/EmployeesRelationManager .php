<?php

namespace App\Filament\Resources\BusinessFunctionResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmployeesRelationManager extends RelationManager
{
    // Il nome del metodo definito nel modello BusinessFunction
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Dipendenti Assegnati';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome Dipendente')
                    ->searchable()
                    ->sortable(),

                // --- VISUALIZZAZIONE DEL RUOLO DI MANAGER ---
                IconColumn::make('pivot.is_manager')
                    ->label('Responsabile / Manager')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                // Filtro rapido per vedere solo i manager del reparto
                TernaryFilter::make('pivot.is_manager')
                    ->label('Solo Responsabili'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                      // --- AGGIUNTA DEL FLAG IN FASE DI ASSOCIAZIONE ---
                      // Quando associ il dipendente, Filament ti mostrerà un checkbox per decidere se è manager
                    ->form(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        Toggle::make('is_manager')
                            ->label('Imposta come Responsabile di questa Funzione')
                            ->default(false),
                    ]),
            ])
            ->actions([
                // Permette di modificare al volo il flag "is_manager" senza dover dissociare il record
                Tables\Actions\EditAction::make()
                    ->form([
                        Toggle::make('pivot.is_manager')
                            ->label('Responsabile / Manager'),
                    ]),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
