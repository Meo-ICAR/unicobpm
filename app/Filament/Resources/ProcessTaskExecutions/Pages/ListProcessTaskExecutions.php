<?php

namespace App\Filament\Resources\ProcessTaskExecutions\Pages;

use App\Filament\Resources\ProcessTaskExecutions\ProcessTaskExecutionResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessTaskExecutions extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTaskExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
