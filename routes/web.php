<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/manuale-operativo-bpm', function () {
    return response()->file(resource_path('manuals/manuale-operativo-bpm.html'));
})->middleware('auth')->name('manuale-operativo-bpm');
