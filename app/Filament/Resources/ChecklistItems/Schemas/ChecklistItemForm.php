<?php

namespace App\Filament\Resources\ChecklistItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChecklistItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('checklist_id')
                    ->relationship('checklist', 'name')
                    ->required(),
                TextInput::make('item_code'),
                TextInput::make('ordine')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('name'),
                TextInput::make('label'),
                Textarea::make('question')
                    ->columnSpanFull(),
                Select::make('type')
                    ->options([
            'boolean' => 'Boolean',
            'text' => 'Text',
            'number' => 'Number',
            'date' => 'Date',
            'select' => 'Select',
            'multiselect' => 'Multiselect',
        ])
                    ->default('boolean')
                    ->required(),
                Textarea::make('options')
                    ->columnSpanFull(),
                Toggle::make('is_required')
                    ->required(),
                TextInput::make('trigger_model'),
                TextInput::make('trigger_field'),
                TextInput::make('trigger_state'),
                TextInput::make('trigger_value'),
                TextInput::make('exclude_field'),
                TextInput::make('exclude_state'),
                TextInput::make('exclude_value'),
                Toggle::make('is_timestamp_update')
                    ->required(),
                Toggle::make('is_knockout')
                    ->required(),
                TextInput::make('knockout_value'),
                TextInput::make('depends_on_code'),
                TextInput::make('depends_on_value'),
            ]);
    }
}
