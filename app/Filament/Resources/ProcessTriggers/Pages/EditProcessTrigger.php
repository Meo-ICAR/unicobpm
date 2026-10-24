<?php

namespace App\Filament\Resources\ProcessTriggers\Pages;

use App\Filament\Resources\ProcessTriggers\ProcessTriggerResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessTrigger extends EditRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTriggerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
