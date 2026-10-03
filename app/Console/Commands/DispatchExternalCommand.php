<?php

namespace App\Console\Commands;

use App\Services\ExternalAppResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchExternalCommand extends Command
{
    protected $signature = 'bpm:dispatch-external-command {app : Chiave dell\'applicativo esterno (es. proforma)} {external_command : Comando in whitelist sull\'applicativo (es. vcoge:calculate)} {--option=* : Opzione del comando nel formato nome=valore (valore omesso = flag attivo)}';

    protected $description = 'Mette in coda un comando Artisan su un applicativo esterno tramite POST /api/commands/{command}';

    public function handle(ExternalAppResolver $appResolver): int
    {
        $app = $this->argument('app');
        $externalCommand = $this->argument('external_command');

        try {
            $response = Http::asJson()
                ->withHeaders(['X-Api-Key' => (string) config('services.bpm.bridge_api_key')])
                ->timeout(15)
                ->connectTimeout(4)
                ->post("{$appResolver->urlFor($app)}/api/commands/{$externalCommand}", ['options' => $this->parseOptions()]);
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

        $this->info("Comando {$externalCommand} messo in coda su {$app}.");

        return self::SUCCESS;
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
