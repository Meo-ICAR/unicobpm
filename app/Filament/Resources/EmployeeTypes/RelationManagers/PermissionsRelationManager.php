<?php

namespace App\Filament\Resources\EmployeeTypes\RelationManagers;

use App\Models\Resource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Matrice Permessi e Accessi';

    public function table(Table $table): Table
    {
        return $table
            // Mostriamo la lista completa delle risorse censite nel sistema
            ->query(Resource::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Risorsa')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Resource $record) => "Chiave: {$record->key}"),

                TextColumn::make('group')
                    ->label('Gruppo')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                /* =========================================================================
                 | TOGGLE MASTER (Abilita / Disabilita tutto per questa risorsa)
                 | ========================================================================= */
                ToggleColumn::make('all_permissions')
                    ->label('Tutti')
                    ->getStateUsing(function (Resource $record): bool {
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $count = $this->getOwnerRecord()->permissions()
                            ->where('resource_id', $record->id)
                            ->whereIn('action', $actions)
                            ->count();

                        return $count === count($actions);
                    })
                    ->updateStateUsing(function (Resource $record, bool $state): void {
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $owner = $this->getOwnerRecord();

                        if ($state) {
                            foreach ($actions as $action) {
                                $owner->permissions()->firstOrCreate([
                                    'resource_id' => $record->id,
                                    'action' => $action,
                                ]);
                            }
                        } else {
                            $owner->permissions()
                                ->where('resource_id', $record->id)
                                ->whereIn('action', $actions)
                                ->delete();
                        }
                    }),

                /* =========================================================================
                 | TOGGLE SINGOLE AZIONI CRUD
                 | ========================================================================= */
                ToggleColumn::make('perm_view_any')
                    ->label('Elenco (viewAny)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->id, 'viewAny'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->id, 'viewAny', $state)),

                ToggleColumn::make('perm_view')
                    ->label('Dettaglio (view)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->id, 'view'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->id, 'view', $state)),

                ToggleColumn::make('perm_create')
                    ->label('Crea (create)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->id, 'create'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->id, 'create', $state)),

                ToggleColumn::make('perm_update')
                    ->label('Modifica (update)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->id, 'update'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->id, 'update', $state)),

                ToggleColumn::make('perm_delete')
                    ->label('Elimina (delete)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPermission($record->id, 'delete'))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePermission($record->id, 'delete', $state)),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('Filtra per Gruppo')
                    ->options(fn () => Resource::query()->whereNotNull('group')->pluck('group', 'group')->toArray()),
            ])
            ->headerActions([
                // Azione Rapida: Concedi TUTTO il sistema a questo Ruolo
                Action::make('grantAllGlobal')
                    ->label('Abilita Tutto')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function () {
                        $resources = Resource::all();
                        $actions = ['viewAny', 'view', 'create', 'update', 'delete'];
                        $owner = $this->getOwnerRecord();

                        foreach ($resources as $resource) {
                            foreach ($actions as $action) {
                                $owner->permissions()->firstOrCreate([
                                    'resource_id' => $resource->id,
                                    'action' => $action,
                                ]);
                            }
                        }
                    }),

                // Azione Rapida: Revoca TUTTI i permessi
                Action::make('removeAllGlobal')
                    ->label('Revoca Tutto')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn () => $this->getOwnerRecord()->permissions()->delete()),
            ]);
    }

    /* =========================================================================
     | HELPER METODI PRIVATI
     | ========================================================================= */

    protected function hasPermission(int $resourceId, string $action): bool
    {
        return $this->getOwnerRecord()->permissions()
            ->where('resource_id', $resourceId)
            ->where('action', $action)
            ->exists();
    }

    protected function togglePermission(int $resourceId, string $action, bool $state): void
    {
        $owner = $this->getOwnerRecord();

        if ($state) {
            $owner->permissions()->firstOrCreate([
                'resource_id' => $resourceId,
                'action' => $action,
            ]);
        } else {
            $owner->permissions()
                ->where('resource_id', $resourceId)
                ->where('action', $action)
                ->delete();
        }
    }
}
