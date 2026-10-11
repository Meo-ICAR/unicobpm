<?php

namespace App\Filament\Resources\ExternalEvents\Pages;

use App\Filament\Resources\ExternalEvents\ExternalEventResource;
use Filament\Resources\Pages\ListRecords;

class ListExternalEvents extends ListRecords
{
    protected static string $resource = ExternalEventResource::class;
}
