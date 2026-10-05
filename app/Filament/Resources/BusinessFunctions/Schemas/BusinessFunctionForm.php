<?php

namespace App\Filament\Resources\BusinessFunctions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BusinessFunctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificazione')
                    ->description('Codice, nome e classificazione della funzione aziendale')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Codice')
                                    ->required(),
                                TextInput::make('name')
                                    ->label('Nome'),
                                Select::make('macro_area')
                                    ->label('Macro area')
                                    ->options([
                                        'Governance' => 'Governance',
                                        'Business / Commerciale' => 'Business / Commerciale',
                                        'Supporto' => 'Supporto',
                                        'Controlli (II Livello)' => 'Controlli (II Livello)',
                                        'Controlli (III Livello)' => 'Controlli (III Livello)',
                                        'Controlli / Privacy' => 'Controlli / Privacy',
                                    ])
                                    ->required(),
                                Select::make('type')
                                    ->label('Tipologia')
                                    ->options([
                                        'Strategica' => 'Strategica',
                                        'Operativa' => 'Operativa',
                                        'Supporto' => 'Supporto',
                                        'Controllo' => 'Controllo',
                                    ])
                                    ->required(),
                            ]),
                    ]),
                Section::make('Esternalizzazione e gestione')
                    ->description('Chi gestisce la funzione e se può essere affidata a un consulente esterno')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('outsourcable_status')
                                    ->label('Esternalizzabile')
                                    ->options([
                                        'yes' => 'Sì',
                                        'no' => 'No',
                                        'partial' => 'Parziale',
                                    ])
                                    ->default('no')
                                    ->required(),
                                TextInput::make('managed_by_code')
                                    ->label('Gestita dal codice'),
                                TextInput::make('email')
                                    ->label('Email di contatto')
                                    ->email(),
                            ]),
                    ]),
                Section::make('Descrizione e responsabilità')
                    ->schema([
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->columnSpanFull(),
                        Textarea::make('mission')
                            ->label('Mission')
                            ->helperText('Cosa fa la funzione')
                            ->columnSpanFull(),
                        Textarea::make('responsibility')
                            ->label('Responsabilità')
                            ->helperText('Elenco di attività e responsabilità')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
