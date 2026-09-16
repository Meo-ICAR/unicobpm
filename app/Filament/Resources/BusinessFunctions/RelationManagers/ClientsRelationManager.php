<?php

namespace App\Filament\Resources\BusinessFunctions\RelationManagers;

use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\EmployeeType;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

    protected static string $relationship = 'clients';

    protected static ?string $title = 'Consulenti esterni';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->employeeTypeSelect(),
            ]);
    }

    /**
     * Il consulente esternalizza un ruolo (EmployeeType) normalmente svolto da un
     * dipendente: qui non c'è un employee_roles da cui limitare le opzioni.
     */
    protected function employeeTypeSelect(): Select
    {
        return Select::make('employee_type_id')
            ->label('Ruolo esternalizzato')
            ->options(EmployeeType::pluck('name', 'id'))
            ->searchable()
            ->required();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('tax_code')
                    ->label('Codice Fiscale')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->searchable(),
                TextColumn::make('pivot.employeeType.name')
                    ->label('Ruolo funzione'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        $this->employeeTypeSelect(),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
