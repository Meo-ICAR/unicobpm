<?php

namespace App\Filament\Resources\ProcessTaskExecutions\Pages;

use App\Filament\Resources\ProcessTaskExecutions\ProcessTaskExecutionResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessTaskExecution extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTaskExecutionResource::class;
}
