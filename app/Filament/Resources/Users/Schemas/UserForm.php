<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Fornitore;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Credenziali di Accesso')
                    ->description('Dati sensibili per l\'autenticazione dell\'utente.')
                    ->columnSpanFull()
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
                    ->columnSpanFull()
                    ->schema([
                        MorphToSelect::make('profile')
                            ->label('Tipo di utente')
                            ->searchable()
                            ->searchPrompt('Scrivi nome o iniziali…')
                            ->preload(false)
                            ->modifyTypeSelectUsing(fn (Select $select) => $select
                                ->label('Tipo di utente')
                                ->placeholder('Dipendente / Consulente / Produttore'))
                            ->modifyKeySelectUsing(fn (Select $select) => $select
                                ->label('Record anagrafica')
                                ->placeholder('Cerca per nome o iniziali…')
                                ->searchPrompt('Scrivi nome o iniziali per filtrare…'))
                            ->types([
                                MorphToSelect\Type::make(Employee::class)
                                    ->label('Dipendente')
                                    ->titleAttribute('name')
                                    ->searchColumns(['name', 'first_name', 'last_name', 'cf', 'email'])
                                    ->getOptionLabelFromRecordUsing(fn (Model $record): string => self::profileLabel($record)),

                                MorphToSelect\Type::make(Client::class)
                                    ->label('Consulente')
                                    ->titleAttribute('name')
                                    ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $query->where('is_company', true))
                                    ->searchColumns(['name', 'first_name', 'last_name', 'vat_number'])
                                    ->getOptionLabelFromRecordUsing(fn (Model $record): string => self::profileLabel($record)),

                                MorphToSelect\Type::make(Fornitore::class)
                                    ->label('Produttore')
                                    ->titleAttribute('name')
                                    ->searchColumns(['name', 'nome', 'piva', 'cf'])
                                    ->getOptionLabelFromRecordUsing(fn (Model $record): string => self::profileLabel($record)),
                            ])
                            ->required(),
                    ]),
            ]);
    }

    private static function profileLabel(Model $record): string
    {
        $name = trim((string) ($record->getAttribute('name') ?: $record->getAttribute('nome') ?: ''));

        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                $record->getAttribute('last_name'),
                $record->getAttribute('first_name'),
            ])));
        }

        $initials = self::initials($name);

        return $initials !== '' ? "{$name} ({$initials})" : ($name !== '' ? $name : '#'.$record->getKey());
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];

        $letters = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $letters[] = mb_strtoupper(mb_substr($part, 0, 1));
        }

        return implode('', $letters);
    }
}
