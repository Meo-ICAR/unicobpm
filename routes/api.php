<?php

use App\Http\Controllers\Api\BpmActivitiesController;
use Illuminate\Support\Facades\Route;

// Consumato da UnicoLoan (e potenzialmente altre app anagrafiche): nessuna
// autenticazione per ora (ambiente non di produzione), da aggiungere prima
// del rilascio.
Route::post('/bpm/available-activities', [BpmActivitiesController::class, 'available'])
    ->name('api.bpm.available-activities');
