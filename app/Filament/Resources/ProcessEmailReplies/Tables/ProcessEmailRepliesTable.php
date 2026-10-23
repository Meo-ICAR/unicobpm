<?php

namespace App\Filament\Resources\ProcessEmailReplies\Tables;

use App\Filament\Utils\ColumnFilters;
use App\Models\ProcessEmailReply;
use App\Services\Replies\Interpretation;
use App\Services\Replies\ProcessReplyApplier;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class ProcessEmailRepliesTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Ricevuta')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('instance.process.name')->label('Processo')->description(fn (ProcessEmailReply $record): string => 'Pratica #'.$record->process_instance_id)->searchable(),
                TextColumn::make('from_email')->label('Da')->searchable(),
                TextColumn::make('excerpt')->label('Risposta')->limit(120)->tooltip(fn (ProcessEmailReply $record): ?string => $record->excerpt)->wrap(),
                TextColumn::make('intent')->label('Interpretazione')->badge()->placeholder('Non capita')
                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                        Interpretation::CONFIRM => 'Conferma',
                        Interpretation::REFUSE => 'Rifiuto',
                        Interpretation::POSTPONE => 'Chiede rinvio',
                        Interpretation::QUESTION => 'Domanda',
                        Interpretation::OTHER => 'Altro',
                        default => $state,
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Interpretation::CONFIRM => 'success',
                        Interpretation::REFUSE => 'danger',
                        default => 'warning',
                    })
                    ->description(fn (ProcessEmailReply $record): ?string => $record->confidence === null ? null : "{$record->confidence}% · ".($record->source === 'ai' ? 'AI' : 'regole')),
                TextColumn::make('proposed_date')->label('Data proposta')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('status')->label('Stato')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ProcessEmailReply::APPLIED => 'Applicata',
                        ProcessEmailReply::NEEDS_REVIEW => 'Da rivedere',
                        ProcessEmailReply::IGNORED => 'Ignorata',
                        default => 'Nessuna azione',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        ProcessEmailReply::APPLIED => 'success',
                        ProcessEmailReply::NEEDS_REVIEW => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Stato')->options([
                    ProcessEmailReply::NEEDS_REVIEW => 'Da rivedere',
                    ProcessEmailReply::APPLIED => 'Applicate',
                    ProcessEmailReply::IGNORED => 'Ignorate',
                    ProcessEmailReply::NO_ACTION => 'Nessuna azione',
                ])->default(ProcessEmailReply::NEEDS_REVIEW),
            ])
            ->recordActions([
                Action::make('conferma')
                    ->label('Conferma e avanza')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ProcessEmailReply $record): bool => $record->status === ProcessEmailReply::NEEDS_REVIEW && $record->process_task_item_id !== null)
                    ->schema([Textarea::make('nota')->label('Nota (facoltativa)')->rows(2)])
                    ->action(function (ProcessEmailReply $record, array $data): void {
                        $record->update(['intent' => Interpretation::CONFIRM]);
                        app(ProcessReplyApplier::class)->apply($record->refresh(), ProcessReplyApplier::ANSWER, auth()->user(), $data['nota'] ?? null);
                        Notification::make()->title('Risposta confermata: il processo avanza')->success()->send();
                    }),
                Action::make('rifiuta')
                    ->label('Registra come rifiuto')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ProcessEmailReply $record): bool => $record->status === ProcessEmailReply::NEEDS_REVIEW && $record->process_task_item_id !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Applica la scelta prevista dall\'azione per il rifiuto (avanza con il rifiuto o sospende il processo); se prevede un operatore, la risposta resta registrata come rifiuto e il processo non avanza.')
                    ->action(function (ProcessEmailReply $record): void {
                        $record->update(['intent' => Interpretation::REFUSE]);
                        $onRefuse = $record->item?->config['on_refuse'] ?? 'review';
                        $how = match ($onRefuse) {
                            'answer' => ProcessReplyApplier::ANSWER,
                            'suspend' => ProcessReplyApplier::SUSPEND,
                            default => null,
                        };

                        if ($how === null) {
                            $record->update(['status' => ProcessEmailReply::APPLIED, 'decided_by' => auth()->id(), 'decided_at' => now()]);
                        } else {
                            app(ProcessReplyApplier::class)->apply($record->refresh(), $how, auth()->user());
                        }

                        Notification::make()->title('Rifiuto registrato')->warning()->send();
                    }),
                Action::make('rinvio')
                    ->label('Concedi rinvio')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->visible(fn (ProcessEmailReply $record): bool => $record->status === ProcessEmailReply::NEEDS_REVIEW && $record->process_task_execution_id !== null)
                    ->schema(fn (ProcessEmailReply $record): array => [
                        DatePicker::make('data')->label('Nuovo termine')->required()->minDate(now()->addDay())->default($record->proposed_date),
                        Checkbox::make('sposta_pratica')->label('Sposta anche la data tassativa della pratica, se la nuova data la supera')
                            ->visible(fn () => $record->instance?->hard_deadline_at !== null),
                    ])
                    ->action(function (ProcessEmailReply $record, array $data): void {
                        $record->update(['intent' => Interpretation::POSTPONE, 'proposed_date' => $data['data']]);
                        app(ProcessReplyApplier::class)->postpone($record->refresh(), Carbon::parse($data['data']), auth()->user(), (bool) ($data['sposta_pratica'] ?? false));
                        $record->update(['status' => ProcessEmailReply::APPLIED, 'decided_by' => auth()->id(), 'decided_at' => now()]);
                        Notification::make()->title('Rinvio concesso')->success()->send();
                    }),
                Action::make('ignora')
                    ->label('Ignora')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->visible(fn (ProcessEmailReply $record): bool => $record->status === ProcessEmailReply::NEEDS_REVIEW)
                    ->requiresConfirmation()
                    ->action(function (ProcessEmailReply $record): void {
                        app(ProcessReplyApplier::class)->ignore($record, auth()->user());
                        Notification::make()->title('Risposta ignorata')->send();
                    }),
            ]);

        return ColumnFilters::apply($table);
    }
}
