<?php

namespace App\Filament\Resources\EmployeeTypes;

use App\Filament\Resources\EmployeeTypes\Pages\ListEmployeeTypes;
use App\Filament\Resources\EmployeeTypes\Pages\ViewEmployeeType;
use App\Filament\Resources\EmployeeTypes\RelationManagers\PermissionsRelationManager;
use App\Filament\Resources\EmployeeTypes\RelationManagers\ResourcePresetsRelationManager;
use App\Filament\Resources\EmployeeTypes\Schemas\EmployeeTypeInfolist;
use App\Filament\Resources\EmployeeTypes\Tables\EmployeeTypesTable;
use App\Filament\Traits\HasFormSubheading;
use App\Filament\Traits\HasPlanAccess;
use App\Models\EmployeeType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Ruoli e uffici in sola lettura: l'organigramma (dati d'ufficio, responsabile, chi ricopre il ruolo) si gestisce in UnicoOAM.
 * Qui si modificano solo i permessi del ruolo sulle schermate del BPM.
 */
class EmployeeTypeResource extends Resource
{
    use HasFormSubheading;
    use HasPlanAccess;

    protected static ?string $model = EmployeeType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static UnitEnum|string|null $navigationGroup = 'BPM';

    protected static ?string $navigationLabel = 'Ruoli e uffici';

    protected static ?string $modelLabel = 'Ruolo';

    protected static ?string $pluralModelLabel = 'Ruoli';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return static::withFormSubheading(EmployeeTypeInfolist::configure($schema));
    }

    public static function getSubheading(): ?string
    {
        return 'Ruoli e uffici usati in RACI, assegnazioni e solleciti.';
    }

    public static function table(Table $table): Table
    {
        return EmployeeTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
            PermissionsRelationManager::class,
            ResourcePresetsRelationManager::class,
        ];
    }

    /** Indirizzo della scheda del ruolo in UnicoOAM (null se l'app non è configurata). */
    public static function unicooamUrl(?Model $record = null): ?string
    {
        $base = config('services.apps.unicooam.url');

        if (blank($base)) {
            return null;
        }

        return rtrim((string) $base, '/').'/admin/employee-types'.($record ? '/'.$record->getKey().'/edit' : '');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeTypes::route('/'),
            'view' => ViewEmployeeType::route('/{record}'),
        ];
    }
}
