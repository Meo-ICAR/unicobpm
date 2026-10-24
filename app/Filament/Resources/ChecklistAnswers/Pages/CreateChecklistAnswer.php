<?php

namespace App\Filament\Resources\ChecklistAnswers\Pages;

use App\Filament\Resources\ChecklistAnswers\ChecklistAnswerResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklistAnswer extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ChecklistAnswerResource::class;
}
