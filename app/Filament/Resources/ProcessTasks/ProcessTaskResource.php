<?php

namespace App\Filament\Resources\ProcessTasks;

use App\Filament\Resources\ProcessTasks\Pages\CreateProcessTask;
use App\Filament\Resources\ProcessTasks\Pages\EditProcessTask;
use App\Filament\Resources\ProcessTasks\Pages\ListProcessTasks;
use App\Filament\Resources\ProcessTasks\RelationManagers\ProcessTaskItemsRelationManager;
use App\Filament\Resources\ProcessTasks\Schemas\ProcessTaskForm;
use App\Filament\Resources\ProcessTasks\Tables\ProcessTasksTable;
use App\Models\ProcessTask;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProcessTaskResource extends Resource
{
    protected static ?string $model = ProcessTask::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'BPM';

    protected static ?int $navigationSort = 4;

    protected static ?string $label = 'Task di Processo';

    protected static ?string $pluralLabel = 'Task di Processo';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProcessTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessTasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProcessTaskItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcessTasks::route('/'),
            'create' => CreateProcessTask::route('/create'),
            'edit' => EditProcessTask::route('/{record}/edit'),
        ];
    }
}
