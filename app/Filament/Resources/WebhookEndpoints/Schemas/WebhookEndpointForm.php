<?php

namespace App\Filament\Resources\WebhookEndpoints\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WebhookEndpointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nome')->required()->maxLength(255),
            TextInput::make('url')->label('Indirizzo che riceve gli eventi')->url()->required()->maxLength(500)
                ->rule(fn () => app()->environment(['local', 'testing']) ? 'regex:#^https?://#' : 'regex:#^https://#')
                ->helperText('Solo https (http ammesso in sviluppo).'),
            TextInput::make('secret')->label('Segreto')->password()->revealable()->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Firma ogni invio: intestazione X-Unico-Signature = sha256=HMAC-SHA256 del corpo. Lasciare vuoto per non cambiarlo.'),
            CheckboxList::make('events')->label('Eventi')->options(['process.status_changed' => 'Cambio di stato di un processo'])
                ->helperText('Nessuno selezionato = tutti gli eventi.')->default([]),
            Toggle::make('is_active')->label('Attivo')->default(true),
        ]);
    }
}
