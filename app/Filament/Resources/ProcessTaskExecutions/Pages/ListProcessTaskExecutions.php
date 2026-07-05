<?php

namespace App\Filament\Resources\ProcessTaskExecutions\Pages;

use App\Filament\Resources\ProcessTaskExecutions\ProcessTaskExecutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessTaskExecutions extends ListRecords
{
    protected static string $resource = ProcessTaskExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
