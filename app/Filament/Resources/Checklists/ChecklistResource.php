<?php

namespace App\Filament\Resources\Checklists;

use App\Filament\Resources\Checklists\Pages\CreateChecklist;
use App\Filament\Resources\Checklists\Pages\EditChecklist;
use App\Filament\Resources\Checklists\Pages\ListChecklists;
use App\Filament\Resources\Checklists\RelationManagers\ChecklistItemRelationManager;
use App\Filament\Resources\Checklists\Schemas\ChecklistForm;
use App\Filament\Resources\Checklists\Tables\ChecklistsTable;
use App\Models\Checklist;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ChecklistResource extends Resource
{
    protected static ?string $model = Checklist::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string|\UnitEnum|null $navigationGroup = 'BPM';
    protected static ?int $navigationSort = 5;
    protected static ?string $label = 'Checklist';
    protected static ?string $pluralLabel = 'Checklist';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ChecklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChecklistsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ChecklistItemRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListChecklists::route('/'),
            'create' => CreateChecklist::route('/create'),
            'edit'   => EditChecklist::route('/{record}/edit'),
        ];
    }
}
