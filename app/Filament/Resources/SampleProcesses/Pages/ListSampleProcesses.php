<?php

namespace App\Filament\Resources\SampleProcesses\Pages;

use App\Filament\Resources\SampleProcesses\SampleProcessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSampleProcesses extends ListRecords
{
    protected static string $resource = SampleProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
