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
                    ->label('Istanza di Processo')
                    ->relationship('processInstance', 'id')
                    ->required(),
                Select::make('process_task_id')
                    ->label('Task di Processo')
                    ->relationship('processTask', 'name')
                    ->required(),
                TextInput::make('assignee_type')
                    ->label('Tipo Assegnatario'),
                TextInput::make('assignee_id')
                    ->label('ID Assegnatario')
                    ->numeric(),
                DateTimePicker::make('started_at')
                    ->label('Avviato il')
                    ->required(),
                DateTimePicker::make('claimed_at')
                    ->label('Preso in Carico il'),
                DateTimePicker::make('completed_at')
                    ->label('Completato il'),
                TextInput::make('escalation_level')
                    ->label('Livello di Escalation')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('due_at')
                    ->label('Scadenza'),
                TextInput::make('mandatory_days_to_complete')
                    ->label('Giorni Tassativi per il Completamento')
                    ->helperText('Facoltativo: se impostato, sovrascrive la scadenza calcolata dal template del task.')
                    ->numeric()
                    ->nullable(),
                TextInput::make('execution_status')
                    ->label('Stato Esecuzione')
                    ->required()
                    ->default('completed'),
            ]);
    }
}
