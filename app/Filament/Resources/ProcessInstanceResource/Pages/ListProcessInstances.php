<?php

namespace App\Filament\Resources\ProcessInstanceResource\Pages;

use App\Filament\Resources\ProcessInstanceResource;
use Filament\Resources\Pages\ListRecords;

class ListProcessInstances extends ListRecords
{
    protected static string $resource = ProcessInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return []; // sola lettura: nessuna azione di creazione
    }
}
