<?php

namespace App\Console\Commands;

use App\Actions\StartProcessAction;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTrigger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\ProcessTrigger;

class BpmSchedulerCommand extends Command
{
    protected $signature = 'bpm:run-scheduler';

    protected $description = 'Scansiona i processi ricorrenti e genera le istanze del giorno';

    public function handle(StartProcessAction $startProcessAction)
    {
        $oggi = now();
        // Inserisci questo blocco dentro il metodo handle() di app/Console/Commands/BpmSchedulerCommand.php

        $idleTriggers = ProcessTrigger::where('event_type', 'idle')
            ->where('is_active', true)
            ->get();

        foreach ($idleTriggers as $trigger) {
            $modelClass = $trigger->model_class;

            if (! class_exists($modelClass)) {
                continue;
            }

            // Costruiamo la query dinamicamente sul modello configurato (es: App\Models\Pratica)
            $query = $modelClass::query();

            // Applichiamo le condizioni (es: status = 'sospeso')
            if (! empty($trigger->conditions)) {
                foreach ($trigger->conditions as $cond) {
                    $query->where($cond['field'], $cond['value']);
                }
            }

            // Filtro temporale: updated_at deve essere più vecchio di X giorni fa
            $query->where('updated_at', '<=', now()->subDays($trigger->idle_days));

            $recordsFermi = $query->get();

            foreach ($recordsFermi as $record) {
                // Evitiamo di lanciare un duplicato del processo di sollecito se ne esiste già uno attivo per questa pratica
                $giaInCorso = ProcessInstance::where('process_id', $trigger->process_id)
                    ->where('subject_type', $modelClass)
                    ->where('subject_id', $record->id)
                    ->where('status', 'in_progress')
                    ->exists();

                if (! $giaInCorso) {
                    app(StartProcessAction::class)->execute($record, $trigger->process_id);
                }
            }
        }
        // Recuperiamo tutti i processi ripetitivi attivi
        $recurringProcesses = Process::where('is_recurring', true)->get();

        foreach ($recurringProcesses as $process) {
            $devePartire = false;

            switch ($process->recurrence_frequency) {
                case 'daily':
                    $devePartire = true;
                    break;

                case 'weekly':
                    // Verifica se il giorno della settimana coincide (1 = Lunedì, 7 = Domenica)
                    $devePartire = ($oggi->dayOfWeekIso == $process->recurrence_day);
                    break;

                case 'monthly':
                    // Verifica se oggi è il giorno del mese stabilito (es. il 10)
                    $devePartire = ($oggi->day == $process->recurrence_day);
                    break;

                case 'yearly':
                    // Ipotizziamo che recurrence_day salvi il giorno dell'anno o fai un controllo personalizzato
                    break;
            }

            if ($devePartire) {
                // Evitiamo duplicati nello stesso giorno
                $exists = ProcessInstance::where('process_id', $process->id)
                    ->whereDate('created_at', $oggi->toDateString())
                    ->exists();

                if (! $exists) {
                    // Avviamo il processo passandogli 'null' come soggetto (scadenza interna generica)
                    $startProcessAction->execute(null, $process->id);
                }
            }
        }

        return Command::SUCCESS;
    }
}
