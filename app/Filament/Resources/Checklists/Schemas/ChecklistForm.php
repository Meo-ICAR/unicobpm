<?php

namespace App\Filament\Resources\Checklists\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChecklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name'),
                TextInput::make('code'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
