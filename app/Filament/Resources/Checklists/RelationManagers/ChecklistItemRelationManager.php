<?php

namespace App\Filament\Resources\Checklists\RelationManagers;

use App\Filament\Resources\Checklists\ChecklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ChecklistItemRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItem';

    protected static ?string $relatedResource = ChecklistResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
