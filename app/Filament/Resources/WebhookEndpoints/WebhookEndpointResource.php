<?php

namespace App\Filament\Resources\WebhookEndpoints;

use App\Filament\Resources\WebhookEndpoints\Pages\CreateWebhookEndpoint;
use App\Filament\Resources\WebhookEndpoints\Pages\EditWebhookEndpoint;
use App\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use App\Filament\Resources\WebhookEndpoints\Schemas\WebhookEndpointForm;
use App\Filament\Resources\WebhookEndpoints\Tables\WebhookEndpointsTable;
use App\Filament\Traits\HasFormSubheading;
use App\Filament\Traits\HasPlanAccess;
use App\Models\WebhookEndpoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/** Terzi che ricevono gli eventi dei processi (firmati con il loro segreto). */
class WebhookEndpointResource extends Resource
{
    use HasFormSubheading;
    use HasPlanAccess;

    protected static ?string $model = WebhookEndpoint::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Webhook';

    protected static ?string $modelLabel = 'Webhook';

    protected static ?string $pluralModelLabel = 'Webhook';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    public static function getSubheading(): ?string
    {
        return 'Notifica sistemi esterni al cambio di stato di una pratica BPM (POST firmato con X-Unico-Signature). Non è il canale verso le altre app della suite.';
    }

    public static function form(Schema $schema): Schema
    {
        return static::withFormSubheading(WebhookEndpointForm::configure($schema));
    }

    public static function table(Table $table): Table
    {
        return WebhookEndpointsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEndpoints::route('/'),
            'create' => CreateWebhookEndpoint::route('/create'),
            'edit' => EditWebhookEndpoint::route('/{record}/edit'),
        ];
    }
}
