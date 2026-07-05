<?php

namespace App\Filament\Resources\ProcessTaskItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProcessTaskItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('process_task_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('action_type')
                    ->required(),
                Toggle::make('is_required')
                    ->required(),
                Select::make('document_type_id')
                    ->relationship('documentType', 'name'),
                TextInput::make('handler_job'),
                TextInput::make('config'),
            ]);
    }
}
