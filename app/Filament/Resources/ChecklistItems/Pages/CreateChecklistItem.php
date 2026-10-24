<?php

namespace App\Filament\Resources\ChecklistItems\Pages;

use App\Filament\Resources\ChecklistItems\ChecklistItemResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklistItem extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ChecklistItemResource::class;
}
