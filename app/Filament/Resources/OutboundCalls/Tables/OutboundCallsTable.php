<?php

namespace App\Filament\Resources\OutboundCalls\Tables;

use App\Models\OutboundCall;
use App\Services\OutboundCallService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OutboundCallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('event')->label('Evento')->searchable(),
                TextColumn::make('app')->label('App')->placeholder('Webhook')->sortable(),
                TextColumn::make('method')->label('Metodo'),
                TextColumn::make('status')->label('Stato')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        OutboundCall::SENT => 'success',
                        OutboundCall::FAILED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        OutboundCall::SENT => 'Consegnata',
                        OutboundCall::FAILED => 'Fallita',
                        default => 'In attesa',
                    })
                    ->sortable(),
                TextColumn::make('attempts')->label('Tentativi')->sortable(),
                TextColumn::make('last_error')->label('Ultimo errore')->placeholder('—')->wrap(),
                TextColumn::make('next_attempt_at')->label('Prossimo tentativo')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('created_at')->label('Creata il')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Stato')->options([
                    OutboundCall::PENDING => 'In attesa',
                    OutboundCall::SENT => 'Consegnata',
                    OutboundCall::FAILED => 'Fallita',
                ]),
            ])
            ->recordActions([
                Action::make('riprova')
                    ->label('Riprova')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (OutboundCall $record): bool => $record->status === OutboundCall::FAILED)
                    ->requiresConfirmation()
                    ->action(function (OutboundCall $record): void {
                        app(OutboundCallService::class)->retry($record);
                        Notification::make()->title('Chiamata rimessa in coda')->success()->send();
                    }),
            ]);
    }
}
