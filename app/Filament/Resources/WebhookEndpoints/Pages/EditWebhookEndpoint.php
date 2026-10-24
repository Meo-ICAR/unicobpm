<?php

namespace App\Filament\Resources\WebhookEndpoints\Pages;

use App\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditWebhookEndpoint extends EditRecord
{
    protected static string $resource = WebhookEndpointResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Notifica sistemi esterni al cambio di stato di una pratica BPM (POST firmato con X-Unico-Signature). Non è il canale verso le altre app della suite.';
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
