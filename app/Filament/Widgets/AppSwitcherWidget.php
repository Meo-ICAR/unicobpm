<?php

namespace App\Filament\Widgets;

use App\Services\ExternalAppResolver;
use App\Services\SsoTokenBroker;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mostra le altre app (UnicoLoan/UnicoOAM/Proforma) in cui l'utente loggato
 * ha anche un account, interrogando la loro API /api/users/lookup, e
 * permette di passare con login automatico riusando il bridge SSO già
 * presente in ognuna di esse (BpmBridgeController + /bpm-landing/{id}).
 */
class AppSwitcherWidget extends Widget
{
    protected string $view = 'filament.widgets.app-switcher-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public function getAvailableApps(): array
    {
        $email = auth()->user()?->email;

        if (blank($email)) {
            return [];
        }

        return Cache::remember(
            'app-switcher:'.$email,
            now()->addMinute(),
            fn () => $this->lookupAvailableApps($email),
        );
    }

    /**
     * @return array<int, array{key: string, label: string, url: string}>
     */
    private function lookupAvailableApps(string $email): array
    {
        $resolver = app(ExternalAppResolver::class);
        $apps = [];

        foreach ($resolver->allApps() as $app) {
            try {
                $response = Http::timeout(4)->connectTimeout(2)
                    ->get("{$resolver->urlFor($app)}/api/users/lookup", ['email' => $email]);

                if ($response->successful() && $response->json('exists') === true) {
                    $apps[] = [
                        'key' => $app,
                        'label' => $resolver->labelFor($app),
                        'url' => $resolver->urlFor($app),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("App switcher: lookup fallita per {$app}.", ['error' => $e->getMessage()]);
            }
        }

        return $apps;
    }

    public function switchTo(string $app)
    {
        $user = auth()->user();
        $resolver = app(ExternalAppResolver::class);

        $token = app(SsoTokenBroker::class)->issueToken($user->email);

        $url = $resolver->urlFor($app).'/bpm-landing/dashboard?'.http_build_query([
            'token' => $token,
            'user_email' => $user->email,
        ]);

        return redirect()->away($url);
    }
}
