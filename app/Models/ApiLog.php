<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Una chiamata API (in ingresso o in uscita) con richiesta, risposta, durata ed eventuale costo AI dei dati trasportati. */
class ApiLog extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['request_headers' => 'array', 'response_headers' => 'array', 'ai_cost' => 'decimal:6'];
    }

    public static function formatCost(null|float|string $cost): ?string
    {
        return $cost === null ? null : '$ '.number_format((float) $cost, 4, ',', '.');
    }

    public function isSuccessful(): bool
    {
        return $this->error === null && $this->status_code !== null && $this->status_code < 400;
    }

    /**
     * Cosa è successo a partire da questa chiamata: le altre chiamate con lo stesso identificativo, l'evento esterno registrato,
     * i processi che ha avviato e i loro task. Ogni voce è [istante, descrizione].
     *
     * @return Collection<int, array{0: Carbon, 1: string}>
     */
    public function timeline(): Collection
    {
        $steps = collect();

        $related = self::query()
            ->where(fn ($q) => $q->when($this->correlation_id, fn ($q) => $q->orWhere('correlation_id', $this->correlation_id))
                ->when($this->event_id, fn ($q) => $q->orWhere('event_id', $this->event_id))
                ->orWhere('id', $this->id))
            ->orderBy('id')->get();

        foreach ($related as $log) {
            $steps->push([$log->created_at, ($log->direction === self::IN ? 'Ricevuta' : 'Inviata').' '.$log->method.' '.$log->url.' → '.($log->status_code ?? $log->error ?? '—').($log->duration_ms !== null ? " ({$log->duration_ms} ms)" : '')]);
        }

        $eventId = $this->event_id ?? $related->pluck('event_id')->filter()->first();
        $event = $eventId ? ExternalEvent::query()->where('event_id', $eventId)->first() : null;

        if ($event !== null) {
            $steps->push([$event->created_at, "Evento «{$event->name}» registrato: {$event->started_count} processi avviati ({$event->status})"]);

            $ref = $event->source && $event->subject_ref ? "{$event->source}:{$event->subject_ref}" : $event->subject_ref;

            foreach ($ref ? ProcessInstance::query()->where('external_ref', $ref)->get() : [] as $instance) {
                $steps->push([$instance->created_at, "Processo avviato: {$instance->title} (#{$instance->id})"]);

                foreach ($instance->taskExecutions()->with('processTask')->get() as $execution) {
                    $steps->push([$execution->created_at, "Task «{$execution->processTask?->name}»: {$execution->execution_status}"]);
                }
            }
        }

        return $steps->sortBy(fn (array $step) => $step[0])->values();
    }
}
