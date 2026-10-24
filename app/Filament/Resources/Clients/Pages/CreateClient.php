<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ClientResource::class;
}
