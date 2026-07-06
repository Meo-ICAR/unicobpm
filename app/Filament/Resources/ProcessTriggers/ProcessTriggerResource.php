<?php

namespace App\Filament\Resources\ProcessTriggers;

use App\Filament\Resources\ProcessTriggers\Pages\CreateProcessTrigger;
use App\Filament\Resources\ProcessTriggers\Pages\EditProcessTrigger;
use App\Filament\Resources\ProcessTriggers\Pages\ListProcessTriggers;
use App\Filament\Resources\ProcessTriggers\Schemas\ProcessTriggerForm;
use App\Filament\Resources\ProcessTriggers\Tables\ProcessTriggersTable;
use App\Models\ProcessTrigger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProcessTriggerResource extends Resource
{
    protected static ?string $model = ProcessTrigger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProcessTriggerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessTriggersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcessTriggers::route('/'),
            'create' => CreateProcessTrigger::route('/create'),
            'edit' => EditProcessTrigger::route('/{record}/edit'),
        ];
    }
}
