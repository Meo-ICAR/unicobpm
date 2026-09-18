<?php

namespace App\Filament\Resources\EmployeeTypes\RelationManagers;

use App\Enums\UserRole;
use App\Models\Resource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Preset di accesso totale (tutte le azioni CRUD) per ruolo/risorsa,
 * affiancati alla matrice granulare di PermissionsRelationManager. Pensati
 * per abilitare in blocco tutte le procedure di un ruolo, gestibili solo da
 * Admin/SuperAdmin (vedi App\Enums\UserRole).
 */
class ResourcePresetsRelationManager extends RelationManager
{
    protected static string $relationship = 'resourcePresets';

    protected static ?string $title = 'Preset Procedure';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $role = UserRole::tryFrom((string) auth()->user()?->role);

        return in_array($role, [UserRole::ADMIN, UserRole::SUPER_ADMIN], true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Resource::query())
            ->columns([
                TextColumn::make('app_name')
                    ->label('App')
                    ->badge()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Procedura')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Resource $record) => "Chiave: {$record->key}"),

                TextColumn::make('group')
                    ->label('Gruppo')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                ToggleColumn::make('preset_enabled')
                    ->label('Preset attivo (accesso totale)')
                    ->getStateUsing(fn (Resource $record) => $this->hasPreset($record->id))
                    ->updateStateUsing(fn (Resource $record, bool $state) => $this->togglePreset($record->id, $state)),
            ])
            ->filters([
                SelectFilter::make('app_name')
                    ->label('Filtra per App')
                    ->options(fn () => Resource::query()->distinct()->pluck('app_name', 'app_name')->toArray()),

                SelectFilter::make('group')
                    ->label('Filtra per Gruppo')
                    ->options(fn () => Resource::query()->whereNotNull('group')->pluck('group', 'group')->toArray()),
            ])
            ->headerActions([
                Action::make('grantAllPresets')
                    ->label('Abilita Tutto')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function () {
                        $owner = $this->getOwnerRecord();

                        foreach (Resource::all() as $resource) {
                            $owner->resourcePresets()->firstOrCreate(['resource_id' => $resource->id]);
                        }
                    }),

                Action::make('revokeAllPresets')
                    ->label('Revoca Tutto')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn () => $this->getOwnerRecord()->resourcePresets()->delete()),
            ]);
    }

    protected function hasPreset(int $resourceId): bool
    {
        return $this->getOwnerRecord()->resourcePresets()
            ->where('resource_id', $resourceId)
            ->exists();
    }

    protected function togglePreset(int $resourceId, bool $state): void
    {
        $owner = $this->getOwnerRecord();

        if ($state) {
            $owner->resourcePresets()->firstOrCreate(['resource_id' => $resourceId]);
        } else {
            $owner->resourcePresets()->where('resource_id', $resourceId)->delete();
        }
    }
}
