<?php

use App\Http\Controllers\Api\ApiLogController;
use App\Http\Controllers\Api\BpmActivitiesController;
use App\Http\Controllers\Api\ExternalEventController;
use App\Http\Controllers\Api\SsoTokenApiController;
use App\Http\Middleware\LogInboundApiCall;
use Illuminate\Support\Facades\Route;
use Unico\Core\Bridge\Http\Middleware\CorrelationId;
use Unico\Core\Bridge\Http\Middleware\VerifyBridgeKey;

// Consumato da UnicoLoan (e potenzialmente altre app anagrafiche): nessuna
// autenticazione per ora (ambiente non di produzione), da aggiungere prima
// del rilascio.
Route::post('/bpm/available-activities', [BpmActivitiesController::class, 'available'])
    ->name('api.bpm.available-activities');

// Consumato dal BpmBridgeController delle altre app (UnicoLoan/UnicoOAM/
// Proforma) per validare i token SSO emessi da UnicoBPM.
Route::post('/verify-token', [SsoTokenApiController::class, 'verify'])
    ->name('api.verify-token');

// Eventi comunicati dalle altre app (es. pratica.perfezionata di unicoagent): avviano i processi con un trigger «evento esterno».
// Chiave condivisa nell'intestazione X-Api-Key (BPM_BRIDGE_API_KEY, come per il bridge); senza chiave configurata risponde 503.
Route::post('/events', [ExternalEventController::class, 'store'])
    ->middleware([CorrelationId::class, LogInboundApiCall::class, VerifyBridgeKey::class, 'throttle:300,1'])
    ->name('api.events.store');

// Esiti delle chiamate API fatte dalle altre app (solo metadati), per il trace «Log API». Stessa chiave degli eventi.
Route::post('/api-logs', [ApiLogController::class, 'store'])
    ->middleware([CorrelationId::class, VerifyBridgeKey::class, 'throttle:600,1'])
    ->name('api.api-logs.store');
