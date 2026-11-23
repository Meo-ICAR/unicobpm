<?php

namespace App\Console\Commands;

use App\Models\Process;
use App\Models\ProcessInstance;
use Illuminate\Console\Command;
use Unico\Core\Models\Document;
use Unico\Core\Models\Fornitore;

/**
 * Retention delle candidature alla rete commerciale non inserite (`config('reclutamento.retention_months')`, 6 mesi): i candidati
 * (produttori con `is_active` = false e decorrenza vuota) la cui candidatura è chiusa da più di quel tempo si cancellano: dati anagrafici
 * azzerati, documenti e file eliminati, pratica conservata solo come traccia anonima. Chi ha una pratica in corso o è stato inserito in rete
 * (decorrenza o attivo) non si tocca. Senza --commit elenca soltanto.
 */
class ReclutamentoRetention extends Command
{
    protected $signature = 'reclutamento:retention {--commit : Cancella davvero (senza, elenca soltanto)} {--months= : Mesi di conservazione (default da config)}';

    protected $description = 'Cancella i dati dei candidati alla rete commerciale con candidatura chiusa da più di 6 mesi (retention)';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: config('reclutamento.retention_months', 6));
        $limit = now()->subMonths($months);
        $process = Process::where('code', 'PRC-RECLUTAMENTO-RETE')->first();

        if ($process === null) {
            $this->warn('Processo PRC-RECLUTAMENTO-RETE non trovato.');

            return self::SUCCESS;
        }

        $due = Fornitore::query()->where('is_active', false)->whereNull('stipulated_at')->get()->filter(function (Fornitore $candidate) use ($process, $limit): bool {
            $instances = ProcessInstance::query()->where('process_id', $process->id)->where('subject_type', $candidate::class)->where('subject_id', $candidate->getKey())->get();

            // Solo candidature chiuse (annullate, rifiutate o archiviate senza seguito) e ferme da più del limite.
            return $instances->isNotEmpty()
                && $instances->every(fn (ProcessInstance $i): bool => in_array($i->status, ['cancelled', 'rejected'], true) || $i->processState?->code === '05')
                && $instances->max('updated_at') < $limit;
        });

        $this->info(($this->option('commit') ? 'CANCELLAZIONE' : 'DRY-RUN')." – candidature chiuse da più di {$months} mesi: {$due->count()}");

        foreach ($due as $candidate) {
            $this->line("#{$candidate->id} ".mb_substr((string) $candidate->name, 0, 40));

            if ($this->option('commit')) {
                $this->erase($candidate, $process);
            }
        }

        return self::SUCCESS;
    }

    private function erase(Fornitore $candidate, Process $process): void
    {
        foreach (Document::query()->where('documentable_type', 'fornitore')->where('documentable_id', $candidate->getKey())->get() as $document) {
            $document->media->each->delete();
            $document->delete();
        }

        ProcessInstance::query()->where('process_id', $process->id)->where('subject_type', $candidate::class)->where('subject_id', $candidate->getKey())->get()
            ->each(function (ProcessInstance $instance): void {
                $instance->forceFill(['title' => 'Candidatura '.$instance->reference.' (dati cancellati per retention)', 'external_data' => null, 'incoming_email_id' => null, 'status' => 'cancelled'])->saveQuietly();
                activity('bpm')->performedOn($instance)->event('retention')->log('Dati del candidato cancellati per retention');
            });

        $candidate->forceFill(array_fill_keys([
            'nome', 'email', 'email_private', 'pec', 'cf', 'piva', 'tel', 'indirizzo', 'comune', 'cap', 'prov', 'citta', 'regione', 'iban', 'iban_holder', 'nomefattura', 'nomecoge', 'description',
        ], null) + ['name' => 'CANDIDATO CANCELLATO'])->save();
        $candidate->delete();
    }
}
