<?php

namespace App\Console\Commands;

use App\Services\ExternalAppClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchExternalCommand extends Command
{
    protected $signature = 'bpm:dispatch-external-command {app : Chiave dell\'applicativo esterno (es. unicocoge)} {external_command : Comando in whitelist sull\'applicativo (es. vcoge:calculate)} {--option=* : Opzione del comando nel formato nome=valore (valore omesso = flag attivo)} {--wait : Attende l\'esito del lancio (lo legge da GET /api/commands/runs/{id}) e fallisce se il comando fallisce} {--timeout=600 : Secondi massimi di attesa con --wait}';

    protected $description = 'Mette in coda un comando Artisan su un applicativo esterno tramite POST /api/commands/{command}; con --wait ne attende l\'esito';

    public function handle(ExternalAppClient $client): int
    {
        $app = $this->argument('app');
        $externalCommand = $this->argument('external_command');

        try {
            $response = $client->for($app)->post($client->url($app, "api/commands/{$externalCommand}"), ['options' => $this->parseOptions()]);
        } catch (\Throwable $e) {
            Log::error("Invio comando {$externalCommand} a {$app} fallito per errore di rete.", ['error' => $e->getMessage()]);
            $this->error("Errore di rete: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($response->failed()) {
            Log::error("Invio comando {$externalCommand} a {$app} rifiutato.", ['status' => $response->status(), 'body' => $response->body()]);
            $this->error("Comando rifiutato da {$app} (HTTP {$response->status()}).");

            return self::FAILURE;
        }

        $runId = $response->json('run_id');
        $this->info("Comando {$externalCommand} messo in coda su {$app}".($runId ? " (lancio #{$runId})." : '.'));

        return $this->option('wait') && $runId ? $this->waitForRun($client, $app, (int) $runId) : self::SUCCESS;
    }

    /** Legge l'esito del lancio finché è finito o scade il tempo; fallisce se il comando non è riuscito. */
    private function waitForRun(ExternalAppClient $client, string $app, int $runId): int
    {
        $deadline = time() + max(1, (int) $this->option('timeout'));

        while (true) {
            try {
                $run = $client->for($app)->get($client->url($app, "api/commands/runs/{$runId}"))->json();
            } catch (\Throwable) {
                $run = null;
            }

            if (($run['finished'] ?? false) === true) {
                $this->line("Esito: {$run['status']} (exit code ".($run['exit_code'] ?? '-').', tentativi '.($run['attempts'] ?? '-').').');

                return $run['status'] === 'succeeded' ? self::SUCCESS : self::FAILURE;
            }

            if (time() >= $deadline) {
                $this->error("Lancio #{$runId} ancora in corso dopo {$this->option('timeout')} secondi: stato ".($run['status'] ?? 'sconosciuto').'.');

                return self::FAILURE;
            }

            sleep(2);
        }
    }

    /**
     * @return array<string, string|bool>
     */
    private function parseOptions(): array
    {
        $options = [];

        foreach ($this->option('option') as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, true);
            $options[$name] = $value;
        }

        return $options;
    }
}
