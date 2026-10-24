<?php

namespace App\Filament\Resources\Processes\Pages;

use App\Filament\Resources\Processes\ProcessResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateProcess extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessResource::class;

    protected function afterCreate(): void
    {
        $this->record->updateNextRunDate();
    }
}
