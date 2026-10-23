<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Filament\Utils\ColumnFilters;
use App\Models\Company;
use App\Models\Fornitore;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->modifyQueryUsing(fn ($query) => $query->with('company'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome attività')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),  // Rende il titolo più visibile
                TextColumn::make('description')
                    ->label('Descrizione')
                    ->sortable()
                    ->searchable()
                    ->limit(50),  // Evita che descrizioni lunghe rompano il layout

                TextColumn::make('taskable')
                    ->label('Collegato a')
                    ->badge()  // Trasforma il testo in un comodo Badge
                    ->color(fn (string $state): string => match ($state) {
                        'company' => 'info',
                        'fornitore' => 'warning',
                        'employee' => 'success',
                        'clienti' => 'purple',
                        'audit' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                // ==========================================
                // NUOVE COLONNE PER LE REGOLE DI ATTIVAZIONE
                // ==========================================
                TextColumn::make('company.name')
                    ->label('Company')
                    ->placeholder('Tutte')
                    ->toggleable(),
                TextColumn::make('trigger_field')
                    ->label('Campo di Controllo')
                    ->placeholder('Nessuno (Sempre attivo)')  // Se è null mostra questo testo grigio
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('trigger_state')
                    ->label('Condizione')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'empty' => 'danger',
                        'filled' => 'success',
                        'equals' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'empty' => 'Deve essere vuoto',
                        'filled' => 'Deve essere pieno',
                        'equals' => 'Deve essere uguale a...',
                        default => '-',
                    })
                    ->placeholder('-'),
                TextColumn::make('trigger_value')
                    ->label('Valore Richiesto')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('trigger_subfield')
                    ->label('Campo 2')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('trigger_subvalue')
                    ->label('Valore 2')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Attivo')
                    ->boolean(),
                // ==========================================
                // NUOVE COLONNE PER LE REGOLE DI ESCLUSIONE
                // ==========================================
                TextColumn::make('exclude_field')
                    ->label('Campo di Controllo')
                    ->placeholder('Nessuno (Sempre attivo)')  // Se è null mostra questo testo grigio
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('exclude_state')
                    ->label('Condizione')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'empty' => 'danger',
                        'filled' => 'success',
                        'equals' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'empty' => 'Deve essere vuoto',
                        'filled' => 'Deve essere pieno',
                        'equals' => 'Deve essere uguale a...',
                        default => '-',
                    })
                    ->placeholder('-'),
                TextColumn::make('exclude_value')
                    ->label('Valore')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray')
                    ->visible(fn ($record) => $record?->exclude_state === 'equals'),  // Nasconde la cella se non serve
            ])
            ->filters([
                // 1. Filtro per isolare l'entità (Azienda, Produttore, ecc.)
                SelectFilter::make('taskable')
                    ->label('Filtra per Entità')
                    ->options([
                        'company' => 'Azienda',
                        'fornitore' => 'Produttore',
                        'employee' => 'Dipendente',
                        'clienti' => 'Mandante',
                        'audit' => 'Audit',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Attivo')
                    ->default(true),
                // 2. Filtro rapido: Task standard VS Task con regole dinamiche
                TernaryFilter::make('has_rules')
                    ->label('Regole di attivazione')
                    ->placeholder('Tutti i task')
                    ->trueLabel('Solo task con regole dinamiche')
                    ->falseLabel('Solo task sempre attivi')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('trigger_field'),
                        false: fn ($query) => $query->whereNull('trigger_field'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                // ... all'interno di ->actions([
                Action::make('clone')
                    ->label('Clona')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Clona Task e Documenti')
                    ->modalDescription('Sei sicuro di voler clonare questo task insieme a tutti i tipi di documento associati?')
                    ->action(function ($record) {
                        // 1. Clona l'istanza del Task
                        $clonedTask = $record->replicate();

                        // Opzionale: Modifica il nome per distinguere il clone
                        $clonedTask->name = $clonedTask->name.' (Copia)';
                        $clonedTask->save();

                        foreach ($record->documentRequirements as $requirement) {
                            $clonedTask->documentRequirements()->create($requirement->only(['document_type_id', 'is_required', 'sort_order', 'documentable', 'client_roles', 'signer_model', 'phase']));
                        }

                        foreach ($record->getMedia('documents') as $media) {
                            $media->copy($clonedTask, 'documents');
                        }

                        Notification::make()
                            ->title('Task clonato con successo!')
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Crea documentazione')
                    ->requiresConfirmation()
                    ->action(function () {
                        $company = Company::first();

                        if (! $company) {
                            Notification::make()->title('Errore')->body('Nessuna azienda trovata.')->danger()->send();

                            return;
                        }

                        $company_id = $company->id;
                        $fornitori = Fornitore::all();

                        // Manteniamo l'Eager Loading per le performance
                        $tasks = Task::where('is_active', true)->with('documentTypes')->get();
                        $createdCount = 0;

                        foreach ($tasks as $task) {
                            if ($task->taskable === 'company' && $task->matchesConditions($company)) {
                                $createdCount += $task->createDocumentation($company_id, $company_id);
                            }

                            if ($task->taskable === 'fornitore') {
                                foreach ($fornitori as $fornitore) {
                                    if ($task->matchesConditions($fornitore)) {
                                        $createdCount += $task->createDocumentation($company_id, $fornitore->id);
                                    }
                                }
                            }
                        }

                        // Notifica finale
                        if ($createdCount > 0) {
                            Notification::make()
                                ->title('Documentazione generata')
                                ->body("Sono stati creati con successo {$createdCount} nuovi documenti mancanti.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Tutto aggiornato')
                                ->body('La documentazione era già completa.')
                                ->info()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return ColumnFilters::apply($table);
    }
}
