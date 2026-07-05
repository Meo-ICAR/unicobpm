<?php

namespace App\Filament\Resources\ProcessTaskExecutions;

use App\Filament\Resources\ProcessTaskExecutions\Pages\CreateProcessTaskExecution;
use App\Filament\Resources\ProcessTaskExecutions\Pages\EditProcessTaskExecution;
use App\Filament\Resources\ProcessTaskExecutions\Pages\ListProcessTaskExecutions;
use App\Filament\Resources\ProcessTaskExecutions\Schemas\ProcessTaskExecutionForm;
use App\Filament\Resources\ProcessTaskExecutions\Tables\ProcessTaskExecutionsTable;
use App\Models\ProcessTaskExecution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProcessTaskExecutionResource extends Resource
{
    protected static ?string $model = ProcessTaskExecution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProcessTaskExecutionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessTaskExecutionsTable::configure($table);
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
            'index' => ListProcessTaskExecutions::route('/'),
            'create' => CreateProcessTaskExecution::route('/create'),
            'edit' => EditProcessTaskExecution::route('/{record}/edit'),
        ];
    }
}
