<?php

namespace App\Filament\Resources\ProcessTaskExecutions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProcessTaskExecutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('process_instance_id')
                    ->relationship('processInstance', 'id')
                    ->required(),
                Select::make('process_task_id')
                    ->relationship('processTask', 'name')
                    ->required(),
                TextInput::make('assignee_type'),
                TextInput::make('assignee_id')
                    ->numeric(),
                DateTimePicker::make('started_at')
                    ->required(),
                DateTimePicker::make('claimed_at'),
                DateTimePicker::make('completed_at'),
                TextInput::make('escalation_level')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('due_at'),
                TextInput::make('execution_status')
                    ->required()
                    ->default('completed'),
            ]);
    }
}
