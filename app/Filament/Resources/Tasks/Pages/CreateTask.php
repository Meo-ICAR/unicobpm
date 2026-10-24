<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = TaskResource::class;
}
