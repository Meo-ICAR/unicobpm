<?php

namespace App\Filament\Resources\WebhookEndpoints;

use App\Filament\Resources\WebhookEndpoints\Pages\CreateWebhookEndpoint;
use App\Filament\Resources\WebhookEndpoints\Pages\EditWebhookEndpoint;
use App\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use App\Filament\Resources\WebhookEndpoints\Schemas\WebhookEndpointForm;
use App\Filament\Resources\WebhookEndpoints\Tables\WebhookEndpointsTable;
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
    use HasPlanAccess;

    protected static ?string $model = WebhookEndpoint::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Webhook';

    protected static ?string $modelLabel = 'Webhook';

    protected static ?string $pluralModelLabel = 'Webhook';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return WebhookEndpointForm::configure($schema);
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
