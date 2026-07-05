<?php

namespace App\Filament\Resources\Processes;

use App\Filament\Resources\Processes\Pages\CreateProcess;
use App\Filament\Resources\Processes\Pages\EditProcess;
use App\Filament\Resources\Processes\Pages\ListProcesses;
use App\Filament\Resources\Processes\RelationManagers\ProcessTasksRelationManager;
use App\Filament\Resources\Processes\Schemas\ProcessForm;
use App\Filament\Resources\Processes\Tables\ProcessesTable;
use App\Models\Process;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProcessResource extends Resource
{
    protected static ?string $model = Process::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'BPM';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Processo';
    protected static ?string $pluralLabel = 'Processi';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProcessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProcessTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProcesses::route('/'),
            'create' => CreateProcess::route('/create'),
            'edit'   => EditProcess::route('/{record}/edit'),
        ];
    }
}
