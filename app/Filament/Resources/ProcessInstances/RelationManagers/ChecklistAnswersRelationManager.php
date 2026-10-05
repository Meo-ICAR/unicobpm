<?php

namespace App\Filament\Resources\ProcessInstances\RelationManagers;

use App\Filament\Resources\ChecklistAnswers\ChecklistAnswerResource;
use App\Filament\Traits\HasRelationPlanAccess;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ChecklistAnswersRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

    protected static string $relationship = 'checklistAnswers';

    protected static ?string $relatedResource = ChecklistAnswerResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
