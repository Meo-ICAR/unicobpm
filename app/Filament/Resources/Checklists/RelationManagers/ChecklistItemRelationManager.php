<?php

namespace App\Filament\Resources\Checklists\RelationManagers;

use App\Filament\Resources\ChecklistItems\Schemas\ChecklistItemForm;
use App\Filament\Traits\HasRelationPlanAccess;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistItemRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

    protected static string $relationship = 'items';

    protected static ?string $title = 'Voci della Checklist';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Stessi campi dello ChecklistItemResource standalone (ChecklistItemForm), unica fonte di
     * verità: sono due interfacce sullo stesso model, non devono più divergere.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione')
                ->columns(2)
                ->schema(ChecklistItemForm::identificationFields()),

            Section::make('Tipo di Risposta')
                ->columns(2)
                ->schema(ChecklistItemForm::answerTypeFields()),

            Section::make('Scrittura Automatica sull\'Anagrafica')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->description('Quando l\'operatore risponde a questa domanda, scrivi il risultato direttamente sul record collegato alla pratica.')
                ->schema(ChecklistItemForm::writeBackFields()),

            Section::make('Regole di Knockout')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema(ChecklistItemForm::knockoutFields()),

            Section::make('Dipendenze')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->description('Mostra questa voce solo se un\'altra domanda ha un certo valore')
                ->schema(ChecklistItemForm::dependencyFields()),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('ordine')->label('#')->sortable(),
                TextColumn::make('item_code')->label('Codice')->placeholder('—'),
                TextColumn::make('label')->label('Voce')->searchable(),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'boolean' => 'gray',
                        'text' => 'info',
                        'number' => 'warning',
                        'date' => 'success',
                        'select' => 'primary',
                        'multiselect' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_required')->label('Obbl.')->boolean(),
                IconColumn::make('is_knockout')->label('KO')->boolean(),
            ])
            ->defaultSort('ordine')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
