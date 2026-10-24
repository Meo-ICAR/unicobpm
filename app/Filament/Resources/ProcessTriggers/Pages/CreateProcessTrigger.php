<?php

namespace App\Filament\Resources\ProcessTriggers\Pages;

use App\Filament\Resources\ProcessTriggers\ProcessTriggerResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessTrigger extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ProcessTriggerResource::class;
}
