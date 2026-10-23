<?php

namespace App\Filament\Resources\EmployeeTypes\Schemas;

use App\Models\EmployeeType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EmployeeTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nome')
                    ->placeholder('-'),
                TextEntry::make('icon')
                    ->label('Icona')
                    ->placeholder('-'),
                TextEntry::make('companytype')
                    ->label('Tipo Azienda')
                    ->placeholder('-'),
                IconEntry::make('is_external')
                    ->label('Ruolo Esternalizzabile')
                    ->boolean()
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->label('Ufficio')
                    ->placeholder('-'),
                TextEntry::make('managed_by_code')
                    ->label('Supervisionato da')
                    ->placeholder('-'),
                TextEntry::make('code')
                    ->label('Codice ufficio')
                    ->placeholder('-'),
                TextEntry::make('macro_area')
                    ->label('Macro area')
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->label('Tipologia')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email dell\'ufficio')
                    ->placeholder('-'),
                TextEntry::make('outsourcable_status')
                    ->label('Esternalizzabile')
                    ->formatStateUsing(fn (?string $state): string => EmployeeType::OUTSOURCABLE[$state] ?? (string) $state),
                TextEntry::make('manager.name')
                    ->label('Responsabile')
                    ->placeholder('-'),
                TextEntry::make('holders')
                    ->label('Chi ricopre il ruolo')
                    ->state(fn (EmployeeType $record): array => $record->employees()->orderBy('name')->pluck('name')
                        ->merge($record->clients()->orderBy('name')->pluck('name')->map(fn (string $name): string => $name.' (esterno)'))
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('Nessuno')
                    ->columnSpanFull(),
                TextEntry::make('mission')
                    ->label('Mission')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('responsibility')
                    ->label('Responsabilità')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
