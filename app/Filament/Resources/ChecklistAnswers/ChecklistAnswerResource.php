<?php

namespace App\Filament\Resources\ChecklistAnswers;

use App\Filament\Resources\ChecklistAnswers\Pages\CreateChecklistAnswer;
use App\Filament\Resources\ChecklistAnswers\Pages\EditChecklistAnswer;
use App\Filament\Resources\ChecklistAnswers\Pages\ListChecklistAnswers;
use App\Filament\Resources\ChecklistAnswers\Schemas\ChecklistAnswerForm;
use App\Filament\Resources\ChecklistAnswers\Tables\ChecklistAnswersTable;
use App\Models\ChecklistAnswer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChecklistAnswerResource extends Resource
{
    protected static ?string $model = ChecklistAnswer::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ChecklistAnswerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChecklistAnswersTable::configure($table);
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
            'index' => ListChecklistAnswers::route('/'),
            'create' => CreateChecklistAnswer::route('/create'),
            'edit' => EditChecklistAnswer::route('/{record}/edit'),
        ];
    }
}
