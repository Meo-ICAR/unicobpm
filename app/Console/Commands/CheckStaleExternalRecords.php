<?php

namespace App\Console\Commands;

use App\Services\EmailSendingService;
use App\Services\ExternalAppResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckStaleExternalRecords extends Command
{
    protected $signature = 'bpm:check-stale-records';

    protected $description = "Invia un reminder se l'ultimo record creato di un modello esterno è più vecchio di N giorni";

    public function handle(ExternalAppResolver $appResolver, EmailSendingService $emailSender): int
    {
        foreach ((array) config('services.stale_record_reminders') as $rule) {
            $createdAt = $this->fetchLatestCreatedAt($appResolver, $rule['app'], $rule['model']);

            if (! $createdAt) {
                continue;
            }

            if ($createdAt->greaterThanOrEqualTo(now()->subDays($rule['max_age_days']))) {
                continue;
            }

            $daysAgo = (int) $createdAt->diffInDays(now());

            $emailSender->send(
                $rule['recipient'],
                "Reminder: nessun nuovo dato {$rule['label']} da {$daysAgo} giorni",
                "L'ultimo record creato per \"{$rule['label']}\" ({$rule['app']}/{$rule['model']}) risale al "
                    .$createdAt->format('d/m/Y').", cioè {$daysAgo} giorni fa (soglia: {$rule['max_age_days']} giorni).\n"
                    .'Verificare che il caricamento dei dati sia attivo.'
            );

            $this->info("Reminder inviato a {$rule['recipient']} per {$rule['app']}/{$rule['model']}.");
        }

        return self::SUCCESS;
    }

    private function fetchLatestCreatedAt(ExternalAppResolver $appResolver, string $app, string $model): ?Carbon
    {
        try {
            $response = Http::asJson()
                ->withHeaders(['X-Api-Key' => (string) config('services.bpm.bridge_api_key')])
                ->timeout(8)
                ->connectTimeout(4)
                ->get("{$appResolver->urlFor($app)}/api/models/{$model}/latest");

            $createdAt = $response->json('fields.created_at');

            if ($response->failed() || ! $createdAt) {
                Log::warning("Ultimo record di {$app}/{$model} non disponibile.", ['status' => $response->status()]);

                return null;
            }

            return Carbon::parse($createdAt);
        } catch (\Throwable $e) {
            Log::warning("Controllo ultimo record di {$app}/{$model} fallito per errore di rete.", ['error' => $e->getMessage()]);

            return null;
        }
    }
}
