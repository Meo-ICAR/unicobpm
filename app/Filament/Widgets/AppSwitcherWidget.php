<?php

namespace App\Filament\Widgets;

use App\Services\ExternalAppClient;
use App\Services\ExternalAppResolver;
use App\Services\SsoTokenBroker;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Mostra le altre app (UnicoFinance/UnicoOAM/Proforma) in cui l'utente loggato
 * ha anche un account, interrogando la loro API /api/users/lookup, e
 * permette di passare con login automatico riusando il bridge SSO già
 * presente in ognuna di esse (BpmBridgeController + /bpm-landing/{id}).
 */
class AppSwitcherWidget extends Widget
{
    protected string $view = 'filament.widgets.app-switcher-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * Nome dell'icona di ogni app nella cartella dei marchi condivisi.
     *
     * @var array<string, string>
     */
    private const BRAND_ICONS = [
        'unicofinance' => 'unico-finance',
        'unicooam' => 'unico-oam',
        'unicocoge' => 'unico-coge',
        'unicogdpr' => 'unico-gdpr',
        'daishboard' => 'dai-shboard',
        'whistle' => 'unico-whistle',
        'unicoaiact' => 'unico-aiact',
    ];

    /**
     * @return array<int, array{key: string, label: string, url: string, logo: ?string, post: bool}>
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
     * @return array<int, array{key: string, label: string, url: string, logo: ?string, post: bool}>
     */
    private function lookupAvailableApps(string $email): array
    {
        $resolver = app(ExternalAppResolver::class);
        $client = app(ExternalAppClient::class);
        $apps = [];

        foreach ($resolver->allApps() as $app) {
            try {
                $response = $client->for($app, 4)->get($client->url($app, 'api/users/lookup'), ['email' => $email]);

                if ($response->successful() && $response->json('exists') === true) {
                    $apps[] = [
                        'key' => $app,
                        'label' => $resolver->labelFor($app),
                        'post' => (bool) config("services.apps.{$app}.sso_post"),
                        'url' => $resolver->urlFor($app),
                        'logo' => $this->logoFor($app),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("App switcher: lookup fallita per {$app}.", ['error' => $e->getMessage()]);
            }
        }

        return $apps;
    }

    /**
     * Icona attuale dell'app (public/brand/icons), se ne esiste una per quella app.
     */
    private function logoFor(string $app): ?string
    {
        $icon = self::BRAND_ICONS[$app] ?? null;

        return $icon !== null && is_file(public_path("brand/icons/{$icon}-64.png"))
            ? asset("brand/icons/{$icon}-64.png")
            : null;
    }

    public function switchTo(string $app)
    {
        $user = auth()->user();
        $resolver = app(ExternalAppResolver::class);

        // Per le app che lo ricevono in POST il token non va mai in un indirizzo: si passa da /passa-a/{app}.
        abort_if((bool) config("services.apps.{$app}.sso_post"), 404);

        $token = app(SsoTokenBroker::class)->issueToken($user->email);

        $url = $resolver->urlFor($app).'/bpm-landing/dashboard?'.http_build_query([
            'token' => $token,
            'user_email' => $user->email,
        ]);

        return redirect()->away($url);
    }
}
