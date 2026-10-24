<?php

namespace App\Filament\Resources\ProcessTaskItems\Pages;

use App\Filament\Resources\ProcessTaskItems\ProcessTaskItemResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessTaskItem extends EditRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTaskItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
