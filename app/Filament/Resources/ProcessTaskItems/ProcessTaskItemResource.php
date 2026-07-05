<?php

namespace App\Filament\Resources\ProcessTaskItems;

use App\Filament\Resources\ProcessTaskItems\Pages\CreateProcessTaskItem;
use App\Filament\Resources\ProcessTaskItems\Pages\EditProcessTaskItem;
use App\Filament\Resources\ProcessTaskItems\Pages\ListProcessTaskItems;
use App\Filament\Resources\ProcessTaskItems\Schemas\ProcessTaskItemForm;
use App\Filament\Resources\ProcessTaskItems\Tables\ProcessTaskItemsTable;
use App\Models\ProcessTaskItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProcessTaskItemResource extends Resource
{
    protected static ?string $model = ProcessTaskItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProcessTaskItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessTaskItemsTable::configure($table);
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
            'index' => ListProcessTaskItems::route('/'),
            'create' => CreateProcessTaskItem::route('/create'),
            'edit' => EditProcessTaskItem::route('/{record}/edit'),
        ];
    }
}
