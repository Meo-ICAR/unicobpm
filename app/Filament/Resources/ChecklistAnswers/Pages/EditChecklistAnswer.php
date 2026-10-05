<?php

namespace App\Filament\Resources\ChecklistAnswers\Pages;

use App\Filament\Resources\ChecklistAnswers\ChecklistAnswerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChecklistAnswer extends EditRecord
{
    protected static string $resource = ChecklistAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
