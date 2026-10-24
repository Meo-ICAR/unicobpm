<?php

namespace App\Filament\Resources\ProcessTriggers\Pages;

use App\Filament\Resources\ProcessTriggers\ProcessTriggerResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessTriggers extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTriggerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
