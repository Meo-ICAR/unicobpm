<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = UserResource::class;
}
