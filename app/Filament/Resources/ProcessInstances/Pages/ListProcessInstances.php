<?php

namespace App\Filament\Resources\ProcessInstances\Pages;

use App\Filament\Resources\ProcessInstances\ProcessInstanceResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessInstances extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = ProcessInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
