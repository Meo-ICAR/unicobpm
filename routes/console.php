<?php

use App\Jobs\ExecutePeriodicProcessJob;
use App\Jobs\MandatoryDeadlineWatchdogJob;
use App\Jobs\TaskEscalationWatchdogJob;
use App\Models\Process;
use Illuminate\Support\Facades\Log;

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
    // Es. tabella non ancora migrata durante un'installazione pulita: non deve bloccare l'avvio di artisan,
    // ma va comunque tracciato per non nascondere problemi reali di connessione al DB.
    Log::warning('Impossibile registrare lo scheduling dei processi ricorrenti a cron_expression.', [
        'exception' => $e->getMessage(),
    ]);
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
})->everyMinute();

// 3. Scansione giornaliera dei processi ricorrenti (daily/weekly/monthly/yearly) e dei trigger "idle"
Schedule::command('bpm:run-scheduler')->dailyAt('06:00');

// 4. Controllo orario degli SLA/escalation sui task pendenti
Schedule::job(new TaskEscalationWatchdogJob)->hourly();

// 5. Controllo orario dei termini tassativi facoltativi (data fine pratica, giorni per singolo step)
Schedule::job(new MandatoryDeadlineWatchdogJob)->hourly();
