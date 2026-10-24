<?php

namespace App\Filament\Resources\Resources\Pages;

use App\Filament\Resources\Resources\ResourceResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateResource extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = ResourceResource::class;
}
