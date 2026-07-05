<?php

namespace App\Filament\Resources\ChecklistAnswers\Pages;

use App\Filament\Resources\ChecklistAnswers\ChecklistAnswerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChecklistAnswers extends ListRecords
{
    protected static string $resource = ChecklistAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
