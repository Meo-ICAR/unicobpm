<?php

namespace App\Filament\Resources\Fornitores\Schemas;

use App\Models\Branch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FornitoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dati Anagrafici e Contatti')
                    ->description("Informazioni personali e di contatto dell'agente o fornitore.")
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('nome')
                                ->label('Ragione Sociale')
                                ->maxLength(255),
                            TextInput::make('piva')
                                ->label('Partita IVA')
                                ->maxLength(20),
                            TextInput::make('name')
                                ->label('Nome Istruttoria')
                                ->maxLength(255),
                            DatePicker::make('available_at')
                                ->label('Data Disponibilità')
                                ->displayFormat('d/m/Y'),
                            Toggle::make('is_active')
                                ->label('Agente Attivo')
                                ->default(true)
                                ->inline(false),
                            DatePicker::make('stipulated_at')
                                ->label('Stipula')
                                ->displayFormat('d/m/Y'),
                            DatePicker::make('dismissed_at')
                                ->label('Cessazione')
                                ->displayFormat('d/m/Y'),
                            TextInput::make('cf')
                                ->label('Codice Fiscale')
                                ->maxLength(16),
                            TextInput::make('tel')
                                ->label('Telefono')
                                ->tel()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Email aziendale o privata se non ancora assegnata')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('pec')
                                ->label('PEC (Posta Elettronica Certificata)')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('email_private')
                                ->label('Email privata')
                                ->disabled(fn($get) => $get('email') === null)
                                ->email()
                                ->maxLength(255),
                            Select::make('branch_id')
                                ->label('Filiale')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ]),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
                // 2. ISCRIZIONI E ALBI (OAM / IVASS)
                Section::make('Iscrizioni e Albi')
                    ->schema([
                        Grid::make(3)->schema([
                            // OAM
                            TextInput::make('oam')
                                ->label('Codice OAM')
                                ->maxLength(30),
                            TextInput::make('numero_iscrizione_rui')
                                ->label('Numero iscrizione OAM (RUI)')
                                ->maxLength(50),
                            TextInput::make('oam_name')
                                ->label('Denominazione OAM')
                                ->maxLength(255)
                                ->columnSpan(2),
                            DatePicker::make('oam_at')
                                ->label('Data iscrizione OAM')
                                ->displayFormat('d/m/Y'),
                            DatePicker::make('oam_dismissed_at')
                                ->label('Data revoca OAM')
                                ->displayFormat('d/m/Y'),
                            // IVASS
                            TextInput::make('ivass')
                                ->label('Codice IVASS')
                                ->maxLength(30),
                            Select::make('ivass_section')
                                ->label('Sezione IVASS')
                                ->options([
                                    'A' => 'A',
                                    'B' => 'B',
                                    'C' => 'C',
                                    'D' => 'D',
                                    'E' => 'E',
                                ]),
                            TextInput::make('ivass_name')
                                ->label('Denominazione IVASS')
                                ->maxLength(255)
                                ->columnSpan(2),
                            DatePicker::make('ivass_at')
                                ->label('Data iscrizione IVASS')
                                ->displayFormat('d/m/Y'),
                        ]),
                    ])
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }
}
