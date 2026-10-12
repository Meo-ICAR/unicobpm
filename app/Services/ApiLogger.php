<?php

namespace App\Services;

use App\Models\ApiLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Registra le chiamate API in `api_logs`. Intestazioni sensibili oscurate, corpi troncati; un errore nel registrare non deve mai
 * far fallire la chiamata che si sta tracciando.
 */
class ApiLogger
{
    private const REDACTED_HEADERS = ['authorization', 'x-api-key', 'x-unico-signature', 'cookie', 'set-cookie', 'idempotency-key'];

    private const MAX_BODY = 65000;

    /** @param array<string,mixed> $attributes */
    public function record(array $attributes): ?ApiLog
    {
        try {
            foreach (['request_body', 'response_body'] as $field) {
                if (isset($attributes[$field])) {
                    $attributes[$field] = mb_strlen((string) $attributes[$field]) > self::MAX_BODY
                        ? mb_substr((string) $attributes[$field], 0, self::MAX_BODY).'… [troncato]'
                        : (string) $attributes[$field];
                }
            }

            foreach (['request_headers', 'response_headers'] as $field) {
                if (isset($attributes[$field])) {
                    $attributes[$field] = $this->redact((array) $attributes[$field]);
                }
            }

            return ApiLog::create($attributes);
        } catch (Throwable $e) {
            Log::warning('Registrazione della chiamata API non riuscita.', ['error' => class_basename($e)]);

            return null;
        }
    }

    /**
     * Uso AI dichiarato nei dati di un evento (`data.ai_usage`).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function aiUsage(array $data): array
    {
        $usage = (array) data_get($data, 'ai_usage', []);

        return array_filter([
            'ai_model' => isset($usage['model']) ? mb_substr((string) $usage['model'], 0, 60) : null,
            'ai_input_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'ai_output_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            'ai_cost' => isset($usage['cost']) ? round((float) $usage['cost'], 6) : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string,mixed>  $headers
     * @return array<string,mixed>
     */
    private function redact(array $headers): array
    {
        $clean = [];

        foreach ($headers as $name => $value) {
            $clean[$name] = in_array(strtolower((string) $name), self::REDACTED_HEADERS, true) ? '***' : (is_array($value) ? implode(', ', $value) : $value);
        }

        return $clean;
    }
}
