<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SsoTokenBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SsoTokenApiController extends Controller
{
    /**
     * Verifica un token SSO emesso da UnicoBPM: consumato dalle altre app
     * tramite il loro BpmBridgeController già esistente
     * (POST {app_url}/api/verify-token {token, email}).
     */
    public function verify(Request $request, SsoTokenBroker $broker): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $valid = $broker->verifyAndConsume($validated['token'], $validated['email']);

        return response()->json(['valid' => $valid]);
    }
}
