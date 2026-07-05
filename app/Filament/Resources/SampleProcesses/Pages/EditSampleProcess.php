<?php

namespace App\Filament\Resources\SampleProcesses\Pages;

use App\Filament\Resources\SampleProcesses\SampleProcessResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSampleProcess extends EditRecord
{
    protected static string $resource = SampleProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
