<?php

namespace App\Filament\Resources\ProcessResource\Pages;

use App\Filament\Resources\ProcessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcesses extends ListRecords
{
    protected static string $resource = ProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
