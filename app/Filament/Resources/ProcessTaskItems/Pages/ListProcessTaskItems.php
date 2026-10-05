<?php

namespace App\Filament\Resources\ProcessTaskItems\Pages;

use App\Filament\Resources\ProcessTaskItems\ProcessTaskItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessTaskItems extends ListRecords
{
    protected static string $resource = ProcessTaskItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
