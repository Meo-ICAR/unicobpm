<?php

namespace App\Filament\Resources\ProcessTasks\Pages;

use App\Filament\Resources\ProcessTasks\ProcessTaskResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessTask extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTaskResource::class;
}
