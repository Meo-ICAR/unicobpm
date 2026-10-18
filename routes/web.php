<?php

use App\Http\Controllers\SsoSwitchController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/manuale-operativo-bpm', function () {
    return response()->file(resource_path('manuals/manuale-operativo-bpm.html'));
})->middleware('auth')->name('manuale-operativo-bpm');

// Passaggio con accesso automatico alle app che accettano il token in POST (services.apps.*.sso_post).
Route::post('/passa-a/{app}', SsoSwitchController::class)->middleware(['auth', 'throttle:20,1'])->name('sso.passa');
