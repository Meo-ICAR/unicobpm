<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

// CORRETTO

class ListEmailTemplates extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
