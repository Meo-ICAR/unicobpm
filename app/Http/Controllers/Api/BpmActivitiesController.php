<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Process;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BpmActivitiesController extends Controller
{
    /**
     * Elenca i processi avviabili su un record esterno, valutando i criteri
     * di attivazione/esclusione di ciascun Process (trigger_field/trigger_state/
     * trigger_value, exclude_field/exclude_state/exclude_value — la stessa
     * logica già usata da App\Models\Task::getAvailableFor()) contro i valori
     * dei campi passati dal chiamante, senza leggere alcuna tabella esterna.
     */
    public function available(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'model_type' => ['required', 'string', 'max:255'],
            'model_id' => ['required', 'string', 'max:255'],
            'fields' => ['array'],
        ]);

        $fields = $validated['fields'] ?? [];

        $processes = Process::where('target_model', $validated['model_type'])
            ->where('is_active', true)
            ->get()
            ->filter(fn (Process $process) => $this->isAvailable($process, $fields))
            ->map(fn (Process $process) => [
                'code' => $process->code,
                'name' => $process->name,
                'description' => $process->description,
            ])
            ->values();

        return response()->json(['activities' => $processes]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function isAvailable(Process $process, array $fields): bool
    {
        if (! empty($process->exclude_field)) {
            $excludeValue = data_get($fields, $process->exclude_field);

            if ($process->exclude_state === 'filled' && ! empty($excludeValue)) {
                return false;
            }

            if ($process->exclude_state === 'empty' && empty($excludeValue)) {
                return false;
            }

            if ($process->exclude_state === 'equals' && $excludeValue == $process->exclude_value) {
                return false;
            }
        }

        if (empty($process->trigger_field)) {
            return true;
        }

        $fieldValue = data_get($fields, $process->trigger_field);

        return match ($process->trigger_state) {
            'filled' => ! empty($fieldValue),
            'empty' => empty($fieldValue),
            'equals' => $fieldValue == $process->trigger_value,
            default => true,
        };
    }
}
