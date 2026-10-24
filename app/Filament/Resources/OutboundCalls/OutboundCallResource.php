<?php

namespace App\Filament\Resources\OutboundCalls;

use App\Filament\Resources\OutboundCalls\Pages\ListOutboundCalls;
use App\Filament\Resources\OutboundCalls\Tables\OutboundCallsTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\OutboundCall;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

/** Chiamate verso le altre app e verso i webhook di terzi, con stato, tentativi ed esito: da qui si ritentano quelle fallite. */
class OutboundCallResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = OutboundCall::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Chiamate in uscita';

    protected static ?string $modelLabel = 'Chiamata in uscita';

    protected static ?string $pluralModelLabel = 'Chiamate in uscita';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    public static function getSubheading(): ?string
    {
        return 'Coda delle scritture e dei webhook in uscita: stato, ritentativi e riprova manuale.';
    }

    public static function table(Table $table): Table
    {
        return OutboundCallsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListOutboundCalls::route('/')];
    }
}
