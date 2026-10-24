<?php

namespace App\Filament\Resources\ProcessInstances\Pages;

use App\Filament\Resources\ProcessInstances\ProcessInstanceResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessInstance extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessInstanceResource::class;
}
