<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Process;
use App\Models\ProcessInstance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DataAnomalyWatchdogJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        // 1. Troviamo il processo di correzione
        $process = Process::where('code', 'correzione_anagrafica')->where('is_active', true)->first();

        if (! $process) {
            return;
        }

        // 2. REGOLA DI BUSINESS: Cerchiamo i clienti attivi con Partita IVA minore di 11 caratteri
        // o con caratteri non numerici (dipende dal tuo database)
        $anomalousCustomers = Customer::where('status', 'active')
            ->whereRaw('LENGTH(partita_iva) < 11')
            ->get();

        foreach ($anomalousCustomers as $customer) {
            // 3. Evitiamo di aprire 10 pratiche uguali per lo stesso cliente se non l'hanno ancora corretta!
            $praticaAperta = ProcessInstance::where('process_id', $process->id)
                ->where('subject_type', Customer::class)
                ->where('subject_id', $customer->id)
                ->whereIn('status', ['pending', 'running'])
                ->exists();

            if (! $praticaAperta) {
                // 4. Scheduliamo il Task di Correzione avviando la pratica
                ProcessInstance::create([
                    'process_id' => $process->id,
                    'subject_type' => Customer::class,
                    'subject_id' => $customer->id,
                    'status' => 'pending',
                    'title' => "Urgente: Correzione P.IVA per {$customer->name}",
                ]);

                Log::info("Anomalia rilevata per Cliente ID {$customer->id}. Schedulato task di correzione.");
            }
        }
    }
}
