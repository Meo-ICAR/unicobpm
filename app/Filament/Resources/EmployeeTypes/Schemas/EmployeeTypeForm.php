<?php

namespace App\Filament\Resources\EmployeeTypes\Schemas;

use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeType;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EmployeeTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ruolo')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->helperText('È il valore che si mette in «Ruoli» di dipendenti ed esterni.')
                            ->required(),
                        TextInput::make('code')
                            ->label('Codice ufficio')
                            ->helperText('Usato dai processi (es. COMPLIANCE).')
                            ->unique(ignoreRecord: true),
                        TextInput::make('icon')
                            ->label('Icona'),
                        TextInput::make('companytype')
                            ->label('Tipo Azienda'),
                        Toggle::make('is_external')
                            ->label('Ruolo esterno'),
                    ]),

                Section::make('Ufficio')
                    ->description('Classificazione e contatti dell\'ufficio che risponde delle attività')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('macro_area')
                            ->label('Macro area')
                            ->options([
                                'Governance' => 'Governance',
                                'Business / Commerciale' => 'Business / Commerciale',
                                'Supporto' => 'Supporto',
                                'Controlli (II Livello)' => 'Controlli (II Livello)',
                                'Controlli (III Livello)' => 'Controlli (III Livello)',
                                'Controlli / Privacy' => 'Controlli / Privacy',
                            ]),
                        Select::make('type')
                            ->label('Tipologia')
                            ->options([
                                'Strategica' => 'Strategica',
                                'Operativa' => 'Operativa',
                                'Supporto' => 'Supporto',
                                'Controllo' => 'Controllo',
                            ]),
                        TextInput::make('email')
                            ->label('Email dell\'ufficio')
                            ->helperText('Usata quando nessuno ricopre il ruolo.')
                            ->email(),
                        TextInput::make('managed_by_code')
                            ->label('Supervisionato dal codice'),
                    ]),

                Section::make('Esternalizzazione e responsabile')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('outsourcable_status')
                            ->label('Esternalizzabile')
                            ->helperText('Se sì o parziale, il ruolo (e il responsabile) può essere anche un esterno.')
                            ->options(EmployeeType::OUTSOURCABLE)
                            ->default('no')
                            ->live()
                            ->required(),
                        MorphToSelect::make('manager')
                            ->label('Responsabile')
                            ->types(fn (Get $get): array => array_filter([
                                MorphToSelect\Type::make(Employee::class)->label('Dipendente')->titleAttribute('name'),
                                in_array($get('outsourcable_status'), ['yes', 'partial'], true)
                                    ? MorphToSelect\Type::make(Client::class)->label('Esterno')->titleAttribute('name')
                                    : null,
                            ]))
                            ->searchable()
                            ->preload(),
                    ]),

                Section::make('Descrizione e responsabilità')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('description')
                            ->label('Descrizione'),
                        Textarea::make('mission')
                            ->label('Mission')
                            ->helperText('Cosa fa l\'ufficio'),
                        Textarea::make('responsibility')
                            ->label('Responsabilità')
                            ->helperText('Elenco di attività e responsabilità'),
                    ]),
            ]);
    }
}
