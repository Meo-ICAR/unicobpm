<?php

namespace App\Filament\Resources\EmployeeTypes\Pages;

use App\Filament\Resources\EmployeeTypes\EmployeeTypeResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeTypes extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = EmployeeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gestisciInUnicooam')
                ->label('Gestisci l\'organigramma in UnicoOAM')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): ?string => EmployeeTypeResource::unicooamUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => EmployeeTypeResource::unicooamUrl() !== null),
        ];
    }
}
