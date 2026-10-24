<?php

namespace App\Filament\Resources\ProcessTaskExecutions\Pages;

use App\Filament\Resources\ProcessTaskExecutions\ProcessTaskExecutionResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessTaskExecution extends EditRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTaskExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
