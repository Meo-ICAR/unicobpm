<?php

use App\Http\Controllers\Api\BpmActivitiesController;
use App\Http\Controllers\Api\SsoTokenApiController;
use Illuminate\Support\Facades\Route;

// Consumato da UnicoLoan (e potenzialmente altre app anagrafiche): nessuna
// autenticazione per ora (ambiente non di produzione), da aggiungere prima
// del rilascio.
Route::post('/bpm/available-activities', [BpmActivitiesController::class, 'available'])
    ->name('api.bpm.available-activities');

// Consumato dal BpmBridgeController delle altre app (UnicoLoan/UnicoOAM/
// Proforma) per validare i token SSO emessi da UnicoBPM.
Route::post('/verify-token', [SsoTokenApiController::class, 'verify'])
    ->name('api.verify-token');
