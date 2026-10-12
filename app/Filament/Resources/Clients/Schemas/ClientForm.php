<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\EmployeeType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Anagrafica')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nominativo / Ragione sociale')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->nullable()
                            ->maxLength(255),
                        TextInput::make('tax_code')
                            ->label('Codice fiscale / P.IVA')
                            ->nullable()
                            ->maxLength(16),
                        Toggle::make('is_person')
                            ->label('Persona fisica')
                            ->default(true),
                        Select::make('employee_roles')
                            ->label('Ruoli')
                            ->multiple()
                            ->options(
                                EmployeeType::query()
                                    ->pluck('name', 'name')
                                    ->map(fn (string $name) => ucfirst($name))
                            )
                            ->searchable()
                            ->nullable()
                            ->helperText('Ruoli EmployeeType associati a questo consulente (stessa struttura degli Employee).'),
                    ]),
            ]);
    }
}
