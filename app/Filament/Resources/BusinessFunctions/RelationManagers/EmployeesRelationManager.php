<?php

namespace App\Filament\Resources\BusinessFunctions\RelationManagers;

use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Employee;
use App\Models\EmployeeType;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class EmployeesRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

    protected static string $relationship = 'employees';

    protected static ?string $title = 'Dipendenti';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_type_id')
                    ->label('Ruolo')
                    ->options(fn (Get $get, ?Employee $record) => $this->employeeTypeOptions($record ?? Employee::find($get('recordId'))))
                    ->live()
                    ->searchable()
                    ->required(),
                Toggle::make('is_manager')
                    ->label('Responsabile')
                    ->default(false),
            ]);
    }

    /**
     * I ruoli selezionabili sono limitati a quelli effettivamente ricoperti dal
     * dipendente (Employee::employee_roles), dato che un dipendente può avere più ruoli.
     *
     * La colonna è JSON, ma la classe App\Models\Employee attiva (app/Models/Employee.php,
     * distinta dall'omonima in app/Models/UNICOOAM/Employee.php) non dichiara il cast
     * 'array' su questo campo: il valore arriva quindi come stringa JSON grezza e va
     * decodificato manualmente, gestendo anche il caso di un singolo ruolo non incapsulato
     * in un array JSON (es. "dipendente" invece di ["dipendente"]).
     */
    protected function employeeTypeOptions(?Employee $employee): array
    {
        return EmployeeType::query()
            ->when($employee, fn ($query) => $query->whereIn('name', $this->employeeRoles($employee)))
            ->pluck('name', 'id')
            ->all();
    }

    protected function employeeRoles(Employee $employee): array
    {
        $roles = $employee->employee_roles;

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        return Arr::wrap($roles);
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
                TextColumn::make('role_title')
                    ->label('Ruolo aziendale')
                    ->searchable(),
                TextColumn::make('department')
                    ->label('Reparto')
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
                        Select::make('employee_type_id')
                            ->label('Ruolo')
                            ->options(fn (Get $get) => $this->employeeTypeOptions(Employee::find($get('recordId'))))
                            ->live()
                            ->searchable()
                            ->required(),
                        Toggle::make('is_manager')
                            ->label('Responsabile')
                            ->default(false),
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
