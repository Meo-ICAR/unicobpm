<?php

namespace App\Filament\Resources\EmployeeTypes\Pages;

use App\Filament\Resources\EmployeeTypes\EmployeeTypeResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeType extends ViewRecord
{
    use HasResourceSubheading;

    protected static string $resource = EmployeeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('modificaInUnicooam')
                ->label('Modifica in UnicoOAM')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): ?string => EmployeeTypeResource::unicooamUrl($this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn (): bool => EmployeeTypeResource::unicooamUrl() !== null),
        ];
    }
}
