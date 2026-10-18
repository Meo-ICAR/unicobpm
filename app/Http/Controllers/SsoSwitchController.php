<?php

namespace App\Http\Controllers;

use App\Services\ExternalAppResolver;
use App\Services\SsoTokenBroker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Passaggio a un'altra app con accesso automatico, con il token monouso in POST: la pagina che risponde invia da sola un
 * modulo a {app}/bpm-landing/dashboard, così il token non finisce in un indirizzo, nella cronologia o nei log.
 */
class SsoSwitchController extends Controller
{
    public function __invoke(Request $request, string $app, ExternalAppResolver $resolver, SsoTokenBroker $tokens): Response
    {
        abort_unless(in_array($app, $resolver->allApps(), true) && config("services.apps.{$app}.sso_post"), 404);

        $email = (string) $request->user()->email;

        return response()->view('sso.passa', [
            'action' => $resolver->urlFor($app).'/bpm-landing/dashboard',
            'label' => $resolver->labelFor($app),
            'token' => $tokens->issueToken($email),
            'email' => $email,
        ])->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
