<?php

namespace App\Filament\Resources\BusinessFunctions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class BusinessFunctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                Select::make('macro_area')
                    ->options([
            'Governance' => 'Governance',
            'Business / Commerciale' => 'Business/ commerciale',
            'Supporto' => 'Supporto',
            'Controlli (II Livello)' => 'Controlli( i i livello)',
            'Controlli (III Livello)' => 'Controlli( i i i livello)',
            'Controlli / Privacy' => 'Controlli/ privacy',
        ])
                    ->required(),
                TextInput::make('name'),
                Select::make('type')
                    ->options([
            'Strategica' => 'Strategica',
            'Operativa' => 'Operativa',
            'Supporto' => 'Supporto',
            'Controllo' => 'Controllo',
        ])
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('outsourcable_status')
                    ->options(['yes' => 'Yes', 'no' => 'No', 'partial' => 'Partial'])
                    ->default('no')
                    ->required(),
                TextInput::make('managed_by_code'),
                Textarea::make('mission')
                    ->columnSpanFull(),
                Textarea::make('responsibility')
                    ->columnSpanFull(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
            ]);
    }
}
