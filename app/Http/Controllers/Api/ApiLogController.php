<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Services\ApiLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Riceve dalle altre app l'esito delle loro chiamate API (`POST /api/api-logs`, chiave condivisa X-Api-Key), così il trace di
 * unicobpm le mostra insieme alle sue. Solo metadati: le app non mandano contenuti, e la chiamata stessa non viene tracciata.
 */
class ApiLogController extends Controller
{
    public function store(Request $request, ApiLogger $logger): JsonResponse
    {
        $data = $request->validate([
            'origin' => ['required', 'string', 'max:40'],
            'method' => ['required', 'string', 'max:10'],
            'url' => ['required', 'string', 'max:500'],
            'status_code' => ['nullable', 'integer', 'between:100,599'],
            'duration_ms' => ['nullable', 'integer', 'min:0'],
            'error' => ['nullable', 'string', 'max:200'],
            'correlation_id' => ['nullable', 'string', 'max:64'],
            'event_id' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', 'integer'],
            'company_vat' => ['nullable', 'string', 'max:20'],
            'ai' => ['nullable', 'array'],
        ]);

        $log = $logger->record([
            'direction' => ApiLog::OUT,
            'company_id' => $logger->companyId($data),
            'origin' => $data['origin'],
            'app' => parse_url($data['url'], PHP_URL_HOST) ?: null,
            'method' => strtoupper($data['method']),
            'url' => $data['url'],
            'status_code' => $data['status_code'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'error' => $data['error'] ?? null,
            'correlation_id' => $data['correlation_id'] ?? null,
            'event_id' => $data['event_id'] ?? null,
        ] + $logger->aiUsage(['ai_usage' => $data['ai'] ?? []]));

        return response()->json(['id' => $log?->id], 201);
    }
}
