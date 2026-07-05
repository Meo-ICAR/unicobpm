<?php

use App\Jobs\ExecutePeriodicProcessJob;
use App\Models\Process;

// 1. Scheduler basato su CRON Espressione (Gira alle scadenze precise)
try {
    $cronProcesses = Process::where('is_periodic', true)
        ->where('is_active', true)
        ->whereNotNull('cron_expression')
        ->get();

    foreach ($cronProcesses as $process) {
        Schedule::job(new ExecutePeriodicProcessJob($process->id))
            ->cron($process->cron_expression);
    }
} catch (Exception $e) {
}

// 2. Controllo Minutario per Rischedulazioni Manuali (Gira ogni minuto)
// Controlla se c'è qualche processo che ha una data 'next_run_at' nel passato o uguale ad ora,
// utile se l'utente ha modificato la data a mano dall'interfaccia.
Schedule::call(function () {
    $manuallyScheduled = Process::where('is_periodic', true)
        ->where('is_active', true)
        ->where('next_run_at', '<=', now())
        ->get();

    foreach ($manuallyScheduled as $process) {
        ExecutePeriodicProcessJob::dispatch($process->id);
    }
})->everyHour();
