<?php

namespace App\Filament\Resources\ProcessInstances\Pages;

use App\Filament\Resources\ProcessInstances\ProcessInstanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessInstance extends EditRecord
{
    protected static string $resource = ProcessInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
