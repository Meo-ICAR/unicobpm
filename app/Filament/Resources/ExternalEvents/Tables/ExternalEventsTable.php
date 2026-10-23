<?php

namespace App\Filament\Resources\ExternalEvents\Tables;

use App\Filament\Utils\ColumnFilters;
use App\Models\ExternalEvent;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ExternalEventsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Ricevuto')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('name')->label('Evento')->searchable()->sortable(),
                TextColumn::make('source')->label('Da')->searchable()->placeholder('—'),
                TextColumn::make('subject_ref')->label('Riferimento')->searchable()->placeholder('—'),
                TextColumn::make('summary')->label('Esito')->state(fn (ExternalEvent $record): ?string => self::summary($record))->placeholder('—')->wrap()
                    ->color(fn (ExternalEvent $record): ?string => ($record->data['status'] ?? null) === 'failed' ? 'danger' : null),
                TextColumn::make('status')->label('Stato')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ExternalEvent::PROCESSED => 'Ha avviato processi',
                        ExternalEvent::IGNORED => 'Nessun trigger',
                        ExternalEvent::FAILED => 'Errore',
                        default => 'Ricevuto',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        ExternalEvent::PROCESSED => 'success',
                        ExternalEvent::FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('started_count')->label('Processi avviati')->sortable(),
            ])
            ->filters([
                SelectFilter::make('name')->label('Evento')->options(fn (): array => ExternalEvent::query()->distinct()->orderBy('name')->pluck('name', 'name')->all()),
                SelectFilter::make('status')->label('Stato')->options([
                    ExternalEvent::PROCESSED => 'Ha avviato processi',
                    ExternalEvent::IGNORED => 'Nessun trigger',
                    ExternalEvent::FAILED => 'Errore',
                ]),
            ])
            ->recordActions([
                Action::make('dati')
                    ->label('Dati')
                    ->icon('heroicon-o-code-bracket')
                    ->modalHeading(fn (ExternalEvent $record): string => $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->modalContent(fn (ExternalEvent $record): HtmlString => new HtmlString('<pre style="white-space:pre-wrap;font-size:.8rem">'.e(json_encode($record->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>')),
            ]);

        return ColumnFilters::apply($table, except: ['summary']);
    }

    /** Riga di sintesi: per l'esito del backup notturno ok/fallito con i database non salvati, per gli altri nulla. */
    private static function summary(ExternalEvent $event): ?string
    {
        if ($event->name !== 'backup.esito') {
            return null;
        }

        $data = (array) $event->data;
        $failed = (array) ($data['databases_failed'] ?? []);
        $warnings = (array) ($data['databases_warnings'] ?? []);
        $line = ($data['status'] ?? '') === 'ok' ? 'Backup riuscito' : 'Backup FALLITO';
        $line .= ' · '.count((array) ($data['databases'] ?? [])).' database';

        if ($failed !== []) {
            $line .= ' · non salvati: '.implode(', ', $failed);
        }

        if ($warnings !== []) {
            $line .= ' · avvisi: '.implode(', ', $warnings);
        }

        return $line;
    }
}
