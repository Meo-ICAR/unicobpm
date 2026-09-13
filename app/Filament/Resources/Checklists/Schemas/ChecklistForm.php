<?php

namespace App\Filament\Resources\Checklists\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChecklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificazione')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('code')
                            ->label('Codice')
                            ->unique(ignoreRecord: true)
                            ->nullable(),
                        Textarea::make('description')
                            ->label('Descrizione')
                            ->nullable()
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Attiva')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
