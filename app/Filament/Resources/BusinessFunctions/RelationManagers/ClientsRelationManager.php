<?php

namespace App\Filament\Resources\BusinessFunctions\RelationManagers;

use App\Filament\Traits\HasRelationPlanAccess;
use App\Models\Client;
use App\Models\EmployeeType;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;

class ClientsRelationManager extends RelationManager
{
    use HasRelationPlanAccess;

    protected static string $relationship = 'clients';

    protected static ?string $title = 'Consulenti esterni';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_type_id')
                    ->label('Ruolo esternalizzato')
                    ->options(fn (Get $get, ?Client $record) => $this->employeeTypeOptions($record ?? Client::find($get('recordId'))))
                    ->live()
                    ->searchable()
                    ->required(),
            ]);
    }

    /**
     * I ruoli selezionabili sono limitati a quelli in Client::employee_roles
     * (stesso pattern degli Employee). Se l'anagrafica non ha ancora ruoli,
     * si mostrano tutti gli EmployeeType così il primo attach può bootstrapparli.
     */
    protected function employeeTypeOptions(?Client $client): array
    {
        $roles = $client ? $this->employeeRoles($client) : [];

        return EmployeeType::query()
            ->when($roles !== [], fn ($query) => $query->whereIn('name', $roles))
            ->pluck('name', 'id')
            ->all();
    }

    protected function employeeRoles(Client $client): array
    {
        $roles = $client->employee_roles;

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        return array_values(array_filter(Arr::wrap($roles), fn ($role) => filled($role)));
    }

    protected function syncAttachedClientRole(array $data): void
    {
        $client = Client::query()->find($data['recordId'] ?? null);
        $typeId = $data['employee_type_id'] ?? null;

        if (! $client || ! $typeId) {
            return;
        }

        $roleName = EmployeeType::query()->whereKey($typeId)->value('name');

        if (filled($roleName)) {
            $client->ensureEmployeeRole($roleName);
        }
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
                        Select::make('employee_type_id')
                            ->label('Ruolo esternalizzato')
                            ->options(fn (Get $get) => $this->employeeTypeOptions(Client::find($get('recordId'))))
                            ->live()
                            ->searchable()
                            ->required(),
                    ])
                    ->after(fn (array $data) => $this->syncAttachedClientRole($data)),
            ])
            ->actions([
                EditAction::make()
                    ->after(function (Client $record, array $data): void {
                        $typeId = $data['employee_type_id'] ?? $record->pivot?->employee_type_id;
                        $roleName = $typeId
                            ? EmployeeType::query()->whereKey($typeId)->value('name')
                            : null;

                        if (filled($roleName)) {
                            $record->ensureEmployeeRole($roleName);
                        }
                    }),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
