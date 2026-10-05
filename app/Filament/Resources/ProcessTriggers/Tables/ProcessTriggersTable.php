<?php

namespace App\Filament\Resources\ProcessTriggers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProcessTriggersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Il Workflow agganciato
                TextColumn::make('process.name')
                    ->label('Workflow Avviato')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // 2. Il Modello Monitorato (pulito dal namespace completo)
                TextColumn::make('model_class')
                    ->label('Modello Target')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => Str::afterLast($state, '\\')),

                // 3. Il tipo di evento con badge colorati per colpo d'occhio
                TextColumn::make('event_type')
                    ->label('Tipo Evento/Trigger')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'idle' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Creazione Record',
                        'updated' => 'Cambio Stato',
                        'idle' => 'Inattività (Sollecito)',
                        default => $state,
                    })
                    ->sortable(),

                // 4. I giorni di inattività (mostrati solo se rilevanti)
                TextColumn::make('idle_days')
                    ->label('Giorni Inattività')
                    ->numeric()
                    ->alignCenter()
                    ->sortable()
                    ->placeholder('-'),

                // 5. Conteggio delle condizioni JSON inserite
                TextColumn::make('conditions')
                    ->label('Condizioni')
                    ->alignCenter()
                    ->formatStateUsing(function (?array $state): string {
                        if (empty($state)) {
                            return 'Nessuna';
                        }
                        $count = count($state);

                        return "{$count} ".($count === 1 ? 'regola' : 'regole');
                    })
                    ->badge()
                    ->color(fn ($state) => $state === 'Nessuna' ? 'gray' : 'info'),

                // 6. Stato Attivo/Disattivo modificabile con un click direttamente dalla lista
                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->filters([
                // Filtro rapido per Tipo Evento
                SelectFilter::make('event_type')
                    ->label('Tipo Trigger')
                    ->options([
                        'created' => 'Creazione Record',
                        'updated' => 'Cambio Stato',
                        'idle' => 'Inattività (Sollecito)',
                    ]),

                // Filtro rapido per Workflow correlato
                SelectFilter::make('process_id')
                    ->label('Workflow')
                    ->relationship('process', 'name'),

                // Filtro rapido per Stato Attivo/Inattivo
                TernaryFilter::make('is_active')
                    ->label('Stato di Attivazione')
                    ->trueLabel('Solo Triggers Attivi')
                    ->falseLabel('Solo Triggers Disattivati')
                    ->placeholder('Tutti'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(), // Ottimo avere anche l'eliminazione sul singolo record
            ])
            ->bulkActions([
                // Nota: In Filament 5 le azioni di massa vanno dentro ->bulkActions() e non toolbarActions
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
