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
                    ->label('Istanza di Processo')
                    ->relationship('processInstance', 'id')
                    ->required(),
                Select::make('checklist_item_id')
                    ->label('Voce Checklist')
                    ->relationship('checklistItem')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_label)
                    ->searchable(['label', 'name'])
                    ->required(),
                Toggle::make('value_boolean')
                    ->label('Valore (Sì/No)'),
                Textarea::make('value_text')
                    ->label('Valore (Testo)')
                    ->columnSpanFull(),
            ]);
    }
}
