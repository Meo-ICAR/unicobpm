<?php

namespace App\Filament\Resources\OutboundCalls\Pages;

use App\Filament\Resources\OutboundCalls\OutboundCallResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\ListRecords;

class ListOutboundCalls extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = OutboundCallResource::class;
}
