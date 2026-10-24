<?php

namespace App\Filament\Resources\ApiLogs\Pages;

use App\Filament\Resources\ApiLogs\ApiLogResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\ViewRecord;

class ViewApiLog extends ViewRecord
{
    use HasResourceSubheading;

    protected static string $resource = ApiLogResource::class;
}
