<?php

namespace App\Filament\Resources\Checklists\Pages;

use App\Filament\Resources\Checklists\ChecklistResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklist extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ChecklistResource::class;
}
