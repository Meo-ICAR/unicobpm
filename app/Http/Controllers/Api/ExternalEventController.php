<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalEvent;
use App\Services\ExternalEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Riceve gli eventi delle altre app (`POST /api/events`, chiave condivisa X-Api-Key): registra l'evento una sola volta e avvia i
 * processi che lo aspettano. Ripetere lo stesso `event_id` risponde 200 con l'esito già registrato, senza avviare nulla.
 */
class ExternalEventController extends Controller
{
    public function store(Request $request, ExternalEventProcessor $processor): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:120'],
            'event' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_.-]+$/'],
            'source' => ['nullable', 'string', 'max:40'],
            'subject_ref' => ['nullable', 'string', 'max:150'],
            'occurred_at' => ['nullable', 'date'],
            'data' => ['nullable', 'array'],
        ]);

        $existing = ExternalEvent::query()->where('event_id', $data['event_id'])->first();

        if ($existing !== null) {
            return $this->resource($existing, 200, duplicate: true);
        }

        $event = ExternalEvent::create([
            'event_id' => $data['event_id'], 'name' => $data['event'], 'source' => $data['source'] ?? null, 'subject_ref' => $data['subject_ref'] ?? null,
            'data' => $data['data'] ?? null, 'occurred_at' => $data['occurred_at'] ?? now(), 'status' => ExternalEvent::RECEIVED,
        ]);

        return $this->resource($processor->process($event), 202);
    }

    private function resource(ExternalEvent $event, int $status, bool $duplicate = false): JsonResponse
    {
        return response()->json(['event_id' => $event->event_id, 'status' => $event->status, 'started' => $event->started_count, 'duplicate' => $duplicate], $status);
    }
}
