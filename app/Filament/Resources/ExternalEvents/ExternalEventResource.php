<?php

namespace App\Filament\Resources\ExternalEvents;

use App\Filament\Resources\ExternalEvents\Pages\ListExternalEvents;
use App\Filament\Resources\ExternalEvents\Tables\ExternalEventsTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\ExternalEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

/** Eventi comunicati dalle altre app (pratica perfezionata, esito del backup notturno...): cosa è arrivato e quanti processi ha avviato. */
class ExternalEventResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = ExternalEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Eventi ricevuti';

    protected static ?string $modelLabel = 'Evento ricevuto';

    protected static ?string $pluralModelLabel = 'Eventi ricevuti';

    protected static UnitEnum|string|null $navigationGroup = 'BPM';

    public static function table(Table $table): Table
    {
        return ExternalEventsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListExternalEvents::route('/')];
    }
}
