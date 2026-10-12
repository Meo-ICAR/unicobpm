<?php

namespace App\Filament\Resources\ApiLogs;

use App\Filament\Resources\ApiLogs\Pages\ListApiLogs;
use App\Filament\Resources\ApiLogs\Pages\ViewApiLog;
use App\Filament\Resources\ApiLogs\Schemas\ApiLogInfolist;
use App\Filament\Resources\ApiLogs\Tables\ApiLogsTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\ApiLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/** Traccia delle chiamate API con le altre app: richiesta, risposta, durata, costo AI e cosa hanno attivato. */
class ApiLogResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = ApiLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Log API';

    protected static ?string $modelLabel = 'Chiamata API';

    protected static ?string $pluralModelLabel = 'Log API';

    protected static UnitEnum|string|null $navigationGroup = 'BPM';

    public static function infolist(Schema $schema): Schema
    {
        return ApiLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiLogs::route('/'),
            'view' => ViewApiLog::route('/{record}'),
        ];
    }
}
