<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('popolaDaAltriTask')
                ->label('Popola da altri task')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('gray')
                ->modalHeading('Popola con i documenti di altri task')
                ->modalDescription('Copia in questo task i documenti richiesti dai task scelti, con destinazione e ruoli. I tipi già presenti restano come sono.')
                ->schema([
                    Select::make('source_ids')
                        ->label('Task')
                        ->options(fn (): array => Task::query()->whereKeyNot($this->getRecord()->getKey())->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data): void {
                    $added = Task::query()->findMany($data['source_ids'])->sum(fn (Task $source): int => $this->getRecord()->importRequirementsFrom($source));
                    $this->fillForm();

                    Notification::make()->title($added.' documenti aggiunti al task')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}
