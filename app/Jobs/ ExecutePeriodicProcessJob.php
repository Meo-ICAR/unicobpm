<?php

namespace App\Jobs;

use App\Models\Process;
use App\Models\ProcessInstance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ExecutePeriodicProcessJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $processId) {}

    public function handle(): void
    {
        $process = Process::find($this->processId);

        // Se attivato manualmente, potremmo voler forzare l'esecuzione anche se is_active è false,
        // ma per sicurezza controlliamo che il processo esista.
        if (! $process) {
            return;
        }

        $targetClass = $process->target_model;
        if (! class_exists($targetClass)) {
            return;
        }

        // 1. Aggiorniamo le date sul processo PRIMA di lanciare il ciclo batch
        $process->update([
            'last_activated_at' => now(),
        ]);

        // Ricalcola il prossimo avvio automatico
        $process->updateNextRunDate();

        // 2. Selezione dei soggetti (es. Clienti)
        $query = $targetClass::query();
        if (! empty($process->target_filters['status'])) {
            $query->where('status', $process->target_filters['status']);
        }
        $subjects = $query->get();

        // 3. Generazione delle istanze di processo
        foreach ($subjects as $subject) {
            ProcessInstance::create([
                'process_id' => $process->id,
                'subject_type' => $targetClass,
                'subject_id' => $subject->id,
                'status' => 'pending',
                'title' => "{$process->name} - ".now()->format('Y-m-d H:i')." (Sogg. ID: {$subject->id})",
            ]);
        }
    }
}
