<?php

namespace App\Filament\Resources\ChecklistAnswers;

use App\Filament\Resources\ChecklistAnswers\Pages\CreateChecklistAnswer;
use App\Filament\Resources\ChecklistAnswers\Pages\EditChecklistAnswer;
use App\Filament\Resources\ChecklistAnswers\Pages\ListChecklistAnswers;
use App\Filament\Resources\ChecklistAnswers\Schemas\ChecklistAnswerForm;
use App\Filament\Resources\ChecklistAnswers\Tables\ChecklistAnswersTable;
use App\Filament\Traits\HasFormSubheading;
use App\Filament\Traits\HasPlanAccess;
use App\Models\ChecklistAnswer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChecklistAnswerResource extends Resource
{
    use HasFormSubheading;
    use HasPlanAccess;

    protected static ?string $model = ChecklistAnswer::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Risposta questionario';

    protected static ?string $pluralModelLabel = 'Risposte questionario';

    public static function getSubheading(): ?string
    {
        return 'Risposte date alle voci dei questionari nelle pratiche.';
    }

    public static function form(Schema $schema): Schema
    {
        return static::withFormSubheading(ChecklistAnswerForm::configure($schema));
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
