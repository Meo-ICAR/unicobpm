<?php

namespace App\Filament\Resources\ChecklistAnswers\Pages;

use App\Filament\Resources\ChecklistAnswers\ChecklistAnswerResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChecklistAnswer extends EditRecord
{
    use HasResourceSubheading;

    protected static string $resource = ChecklistAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
