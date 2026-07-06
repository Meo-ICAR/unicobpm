<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Consultant;
use App\Models\Employee;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Credenziali di Accesso')
                    ->description('Dati sensibili per l\'autenticazione dell\'utente.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome Utente Account')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email di Login')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Collegamento Anagrafica (RACI)')
                    ->description('Associa questo account di login a un profilo reale aziendale.')
                    ->schema([

                        // IL COMPONENTE POLIMORFO NATIVO
                        MorphToSelect::make('profile')
                            ->label('Tipo di Profilo')
                            ->placeholder('Seleziona la tipologia di operatore')
                            ->types([

                                // Configurazione per i Dipendenti
                                MorphToSelect\Type::make(Employee::class)
                                    ->label('Dipendente')
                                    ->titleAttribute('name') // Il campo da mostrare nella select
                                    ->searchable()
                                    ->preload(),

                                // Configurazione per i Consulenti
                                MorphToSelect\Type::make(Consultant::class)
                                    ->label('Consulente Esterno')
                                    ->titleAttribute('name')
                                    ->searchable()
                                    ->preload(),
                            ])
                            ->required() // Rendo il profilo obbligatorio per l'architettura BPM
                            ->searchable()
                            ->preload(),

                    ]),
            ]);
    }
}
