<?php

namespace App\Filament\Resources\ProcessInstances\Pages;

use App\Filament\Actions\CompleteCurrentTaskAction;
use App\Filament\Resources\ProcessInstances\ProcessInstanceResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessInstance extends EditRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CompleteCurrentTaskAction::make(),
            DeleteAction::make(),
        ];
    }
}
