<?php

namespace App\Filament\Resources\ApiLogs\Tables;

use App\Filament\Utils\ColumnFilters;
use App\Models\ApiLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class ApiLogsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('direction')->label('Verso')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ApiLog::IN ? 'Ricevuta' : 'Inviata')
                    ->color(fn (string $state): string => $state === ApiLog::IN ? 'info' : 'gray'),
                TextColumn::make('origin')->label('Fatta da')->placeholder('unicobpm')->searchable(),
                TextColumn::make('company.name')->label('Azienda')->placeholder('—')->toggleable(),
                TextColumn::make('app')->label('Con')->searchable()->placeholder('—'),
                TextColumn::make('method')->label('Metodo'),
                TextColumn::make('url')->label('Indirizzo')->searchable()->limit(60)->tooltip(fn (ApiLog $record): string => $record->url),
                TextColumn::make('status_code')->label('Esito')->badge()->placeholder('—')
                    ->formatStateUsing(fn ($state, ApiLog $record): string => (string) ($state ?? $record->error ?? '—'))
                    ->color(fn (ApiLog $record): string => $record->isSuccessful() ? 'success' : 'danger'),
                TextColumn::make('duration_ms')->label('Durata')->suffix(' ms')->sortable()->placeholder('—'),
                TextColumn::make('ai_cost')->label('Costo AI')->placeholder('—')->sortable()
                    ->formatStateUsing(fn ($state): ?string => ApiLog::formatCost($state))
                    ->summarize(Sum::make()->label('Totale')->formatStateUsing(fn ($state): ?string => ApiLog::formatCost($state))),
                TextColumn::make('event_id')->label('Evento')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('correlation_id')->label('Correlazione')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('company_id')->label('Azienda')->relationship('company', 'name')->searchable()->preload(),
                Filter::make('periodo')->label('Periodo')->schema([
                    DatePicker::make('dal')->label('Dal'),
                    DatePicker::make('al')->label('Al'),
                ])->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['dal'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['al'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date));
                }),
                SelectFilter::make('direction')->label('Verso')->options([ApiLog::IN => 'Ricevute', ApiLog::OUT => 'Inviate']),
                Filter::make('errori')->label('Solo errori')
                    ->query(fn ($query) => $query->where(fn ($q) => $q->whereNotNull('error')->orWhere('status_code', '>=', 400))),
                Filter::make('con_costo')->label('Con costo AI')->query(fn ($query) => $query->whereNotNull('ai_cost')),
            ])
            ->headerActions([
                ExportAction::make()->label('Esporta Excel'),
            ])
            ->recordActions([ViewAction::make()->label('Dettaglio')]);

        return ColumnFilters::apply($table);
    }
}
