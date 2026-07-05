<?php

namespace App\Filament\Resources\Fornitores\Tables;

use App\Filament\Actions\AdvanceProcessAction;
use App\Filament\Actions\StartProcessAction;
use App\Filament\Exports\DynamicGroupExport;
use App\Models\Process;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;  // Importante per il form nel modal
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class FornitoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        DynamicGroupExport::make(),
                        //    ->groupBy('Produttore')  // Campo per il raggruppamento
                        //    ->sumColumns(['Provvigione']),  // Campi da sommare
                    ])
                    ->label('Esporta Excel')
                    ->color('success'),
            ])
            ->selectable('is_active = 1')
            ->columns([
                // DATI PRINCIPALI
                TextColumn::make('nome')
                    ->label('Ragione Sociale')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                ToggleColumn::make('is_active')
                    ->label('Attivo')
                    //  ->boolean()
                    ->sortable(),
                TextColumn::make('piva')
                    ->label('P. IVA')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('stipulated_at')
                    ->label('Mandato')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pec')
                    ->label('PEC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ivass')
                    ->label('IVASS')
                    //  ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // STATO E INQUADRAMENTO
                ToggleColumn::make('isdipendente')
                    ->label('Dipendente')
                    //     ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('enasarco')
                    ->label('Enasarco')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // ALBI PROFESSIONALI (nascosti di default per non affollare la vista)
                TextColumn::make('branch.name')
                    ->label('Filiale')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // DATE
            ])
            ->filters([
                // Filtro per stato attivo/inattivo
                TernaryFilter::make('is_active')
                    ->label('Stato Agente')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo Attivi')
                    ->falseLabel('Solo Inattivi')
                    ->default(true),
                // Filtro per tipologia di mandato Enasarco
                SelectFilter::make('enasarco')
                    ->label('Mandato Enasarco')
                    ->options([
                        'no' => 'Nessuno',
                        'monomandatario' => 'Monomandatario',
                        'plurimandatario' => 'Plurimandatario',
                        'societa' => 'Società',
                    ]),
                // Filtro per natura del collaboratore
                TernaryFilter::make('isdipendente')
                    ->label('Contratto')
                    ->placeholder('Tutti')
                    ->trueLabel('Solo Dipendenti')
                    ->falseLabel('Solo P. IVA / Agenzie'),
                Filter::make('dismessed_at')
                    ->label('Cessati')
                    ->query(fn ($query) => $query->whereNotNull('dismessed_at')),
            ])
            ->recordActions([
                EditAction::make(),

                // Il tasto "Prendi in carico" che abbiamo fatto prima userà l'helper canBeClaimedBy
                Action::make('claim')
                    ->label('Prendi in carico')
                    ->icon('heroicon-o-hand-raised')
                    ->color('success')
                    ->visible(function ($record) {
                        // 1. Recuperiamo la pratica attiva in corso per questo fornitore
                        $activeInstance = $record->processInstances()
                            ->where('status', 'in_progress')
                            ->first();

                        // 2. Se non c'è una pratica attiva, il bottone non deve essere visibile
                        if (! $activeInstance) {
                            return false;
                        }

                        // 3. Chiamiamo il metodo sulla ProcessInstance (non sul Fornitore!)
                        return $activeInstance->canBeClaimedBy(auth()->user());
                    })
                    ->action(function ($record) {
                        $user = auth()->user();

                        // Aggiorna l'istanza master
                        $record->update([
                            'current_assignee_id' => $user->id,
                            'current_assignee_type' => get_class($user),
                        ]);

                        // Aggiorna la tabella transizionale delle esecuzioni registrando il claimed_at
                        $record->currentTaskExecution?->update([
                            'assignee_type' => get_class($user),
                            'assignee_id' => $user->id,
                            'claimed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Pratica presa in carico')
                            ->success();
                    }),
                Action::make('startProcess')
                    ->label('Avvia Pratica')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->form([
                        Select::make('process_id')
                            ->label('Seleziona il Processo da avviare')
                            ->options(Process::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    // Iniettiamo la classe StartProcessAction direttamente nei parametri
                    ->action(function (array $data, $record, StartProcessAction $action): void {

                        // Eseguiamo la logica centralizzata
                        $action->execute($record, $data['process_id']);

                        Notification::make()
                            ->title('Pratica avviata con successo')
                            ->success()
                            ->send();
                    }),
                Action::make('completeStep')
                    ->label('Completa e Avanza')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record, AdvanceProcessAction $advanceAction) {

                        // Eseguiamo l'avanzamento passando l'istanza e l'utente loggato
                        $advanceAction->execute($record, auth()->user(), 'complete');

                        Notification::make()
                            ->title('Task completato')
                            ->description('La pratica è stata spostata con successo allo step successivo.')
                            ->success()
                            ->send();
                    }),
                Action::make('rejectStep')
                    ->label('Respingi e Torna Indietro')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record, AdvanceProcessAction $advanceAction) {
                        // Eseguiamo il rewind passando l'istanza e l'utente loggato
                        $advanceAction->execute($record, auth()->user(), 'reject');

                        Notification::make()
                            ->title('Task respinto')
                            ->description('La pratica è stata riportata allo step precedente.')
                            ->success()
                            ->send();
                    }),
                Action::make('completeStep')
                    ->label('Completa Step')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
    // MOSTRA IL BOTTONE SOLO SE IL FORNITORE HA UNA PRATICA IN CORSO
                    ->visible(fn ($record) => $record->processInstances()->where('status', 'in_progress')->exists())
                    ->action(function ($record, AdvanceProcessAction $advanceAction) {

                        // Recuperiamo l'istanza attiva del fornitore
                        $activeInstance = $record->processInstances()->where('status', 'in_progress')->first();

                        if ($activeInstance) {
                            $advanceAction->execute($activeInstance, auth()->user(), 'complete');

                            Notification::make()
                                ->title('Task completato')
                                ->description('La pratica del fornitore è avanzata allo step successivo.')
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('rejectStep')
                    ->label('Respingi Step')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    // MOSTRA IL BOTTONE SOLO SE IL FORNITORE HA UNA PRATICA IN CORSO
                    ->visible(fn ($record) => $record->processInstances()->where('status', 'in_progress')->exists())
                    ->action(function ($record, AdvanceProcessAction $advanceAction) {
                        // Recuperiamo l'istanza attiva del fornitore
                        $activeInstance = $record->processInstances()->where('status', 'in_progress')->first();

                        if ($activeInstance) {
                            $advanceAction->execute($activeInstance, auth()->user(), 'reject');

                            Notification::make()
                                ->title('Task respinto')
                                ->description('La pratica del fornitore è stata riportata allo step precedente.')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([

                ]),
            ])
            ->emptyStateHeading('Nessun fornitore trovato')
            ->emptyStateDescription('Crea un nuovo fornitore o agente per iniziare.');
    }
}
