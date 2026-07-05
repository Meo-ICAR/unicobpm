<?php

namespace App\Filament\Resources\ProcessInstances\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProcessInstancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. PROCESSO & STEP ATTUALE
                TextColumn::make('process.name')
                    ->label('Processo Macro')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('currentTask.name')
                    ->label('Step Attivo')
                    ->searchable()
                    ->placeholder('Nessuno step attivo')
                    ->description(fn ($record) => $record->status === 'completed' ? 'Processo Terminato' : 'In attesa di lavorazione'),

                // 2. RISOLUZIONE POLIMORFICA DEL SOGGETTO (es. Dipendente, Fornitore)
                TextColumn::make('subject')
                    ->label('Oggetto della Pratica')
                    ->state(fn ($record) => $record->subject ? ($record->subject->name ?? $record->subject->title ?? 'ID: '.$record->subject_id) : '-')
                    ->description(fn ($record) => $record->subject_type ? class_basename($record->subject_type) : null)
                    ->searchable(query: function ($query, string $search) {
                        // Consente la ricerca se i modelli polimorfi hanno la colonna 'name'
                        return $query->whereHasMorph('subject', ['*'], function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),

                // 3. STATO CON BADGE COLORATI
                TextColumn::make('status')
                    ->label('Stato Globale')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'rejected',
                        'slate' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'In Attesa',
                        'in_progress' => 'In Lavorazione',
                        'completed' => 'Completato',
                        'rejected' => 'Rifiutato',
                        'cancelled' => 'Annullato',
                        default => $state,
                    }),

                // 4. RISOLUZIONE POLIMORFICA DELL'ASSEGNATARIO
                TextColumn::make('currentAssignee')
                    ->label('In carico a')
                    ->state(fn ($record) => $record->currentAssignee->name ?? 'Disponibile in coda')
                    ->description(fn ($record) => $record->current_assignee_type ? class_basename($record->current_assignee_type) : 'Nessun operatore')
                    ->color(fn ($record) => $record->current_assignee_id ? 'default' : 'warning')
                    ->icon(fn ($record) => $record->current_assignee_id ? 'heroicon-m-user' : 'heroicon-m-exclamation-triangle'),

                // 5. AZIENDA / AGENZIA DI COMPETENZA
                TextColumn::make('company_id')
                    ->label('Hub / Agency ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                // 6. SOLLECITI ACCORPATI IN UN'UNICA COLONNA OPERATIVA
                TextColumn::make('reminders_sent_count')
                    ->label('Solleciti')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 2 ? 'danger' : 'gray')
                    ->description(fn ($record) => $record->last_reminder_sent_at ? 'Ultimo: '.$record->last_reminder_sent_at->format('d/m H:i') : 'Nessuno inviato'),

                // 7. TIMESTAMPS DI CHIUSURA E CREAZIONE
                TextColumn::make('completed_at')
                    ->label('Chiuso il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Data Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                // Filtro rapido per selezionare il tipo di processo macro
                SelectFilter::make('process_id')
                    ->relationship('process', 'name')
                    ->label('Filtra per Processo')
                    ->preload(),

                // Filtro per stato
                SelectFilter::make('status')
                    ->label('Stato Pratica')
                    ->options([
                        'pending' => 'In Attesa',
                        'in_progress' => 'In Lavorazione',
                        'completed' => 'Completati',
                        'rejected' => 'Rifiutati',
                        'cancelled' => 'Annullati',
                    ]),
            ])
            ->actions([
                // ==========================================
                // AZIONE RACI: PRENDI IN CARICO DIRETTAMENTE
                // ==========================================
                Action::make('claim')
                    ->label('Prendi in carico')
                    ->icon('heroicon-o-hand-raised')
                    ->color('success')
                    // Visibile solo se non è assegnato, è in_progress e l'utente loggato ha i requisiti RACI
                    ->visible(fn ($record) => is_null($record->current_assignee_id) && $record->status === 'in_progress' && method_exists($record, 'canBeClaimedBy') && $record->canBeClaimedBy(auth()->user()))
                    ->action(function ($record) {
                        $user = auth()->user();
                        $record->update([
                            'current_assignee_id' => $user->id,
                            'current_assignee_type' => get_class($user),
                        ]);

                        // Se hai implementato la tabella delle esecuzioni, qui puoi aggiornare anche il claimed_at di process_task_executions

                        Notification::make()
                            ->title('Pratica presa in carico con successo')
                            ->success()
                            ->send();
                    }),

                // Azione standard di modifica / visualizzazione
                EditAction::make()
                    ->label('Gestisci'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
