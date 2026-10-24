<?php

namespace App\Filament\Resources\ExternalEvents\Pages;

use App\Filament\Resources\ExternalEvents\ExternalEventResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\ListRecords;

class ListExternalEvents extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = ExternalEventResource::class;
}
