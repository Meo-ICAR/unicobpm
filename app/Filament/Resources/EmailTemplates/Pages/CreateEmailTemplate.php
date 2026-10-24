<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    use HasResourceSubheading;

    protected static string $resource = EmailTemplateResource::class;
}
