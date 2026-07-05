<?php

namespace App\Filament\Resources\ProcessTaskExecutions\Pages;

use App\Filament\Resources\ProcessTaskExecutions\ProcessTaskExecutionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessTaskExecution extends EditRecord
{
    protected static string $resource = ProcessTaskExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
