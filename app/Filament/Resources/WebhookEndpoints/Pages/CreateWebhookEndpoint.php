<?php

namespace App\Filament\Resources\WebhookEndpoints\Pages;

use App\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateWebhookEndpoint extends CreateRecord
{
    protected static string $resource = WebhookEndpointResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Notifica sistemi esterni al cambio di stato di una pratica BPM (POST firmato con X-Unico-Signature). Non è il canale verso le altre app della suite.';
    }
}
