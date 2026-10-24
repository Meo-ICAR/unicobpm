<?php

namespace App\Filament\Resources\ProcessTaskItems\Pages;

use App\Filament\Resources\ProcessTaskItems\ProcessTaskItemResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessTaskItem extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTaskItemResource::class;
}
