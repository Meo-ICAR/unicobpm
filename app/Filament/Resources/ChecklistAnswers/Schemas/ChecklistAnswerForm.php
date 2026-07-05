<?php

namespace App\Filament\Resources\ChecklistAnswers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ChecklistAnswerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('process_instance_id')
                    ->relationship('processInstance', 'id')
                    ->required(),
                Select::make('checklist_item_id')
                    ->relationship('checklistItem', 'name')
                    ->required(),
                Toggle::make('value_boolean'),
                Textarea::make('value_text')
                    ->columnSpanFull(),
            ]);
    }
}
