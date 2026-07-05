<?php

namespace App\Filament\Resources\ProcessInstances;

use App\Filament\Resources\ProcessInstances\Pages\CreateProcessInstance;
use App\Filament\Resources\ProcessInstances\Pages\EditProcessInstance;
use App\Filament\Resources\ProcessInstances\Pages\ListProcessInstances;
use App\Filament\Resources\ProcessInstances\Schemas\ProcessInstanceForm;
use App\Filament\Resources\ProcessInstances\Tables\ProcessInstancesTable;
use App\Models\ProcessInstance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProcessInstanceResource extends Resource
{
    protected static ?string $model = ProcessInstance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProcessInstanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessInstancesTable::configure($table);
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
            'index' => ListProcessInstances::route('/'),
            'create' => CreateProcessInstance::route('/create'),
            'edit' => EditProcessInstance::route('/{record}/edit'),
        ];
    }
}
