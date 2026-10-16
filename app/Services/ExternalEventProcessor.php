<?php

namespace App\Services;

use App\Filament\Actions\StartProcessAction;
use App\Models\ExternalEvent;
use App\Models\ProcessTrigger;
use Illuminate\Support\Facades\Log;
use Throwable;
use Unico\Core\Models\Company;

/**
 * Un evento comunicato da un'altra app avvia i processi dei trigger «evento esterno» che lo aspettano (nome uguale e condizioni
 * rispettate sui dati dell'evento). Il processo nasce senza soggetto locale: porta il riferimento e i dati dell'evento
 * (`external_ref`, `external_data`) e, se i dati indicano la partita IVA della company, la company.
 */
class ExternalEventProcessor
{
    public function __construct(private StartProcessAction $start) {}

    public function process(ExternalEvent $event): ExternalEvent
    {
        $started = 0;
        $failed = false;

        foreach (ProcessTrigger::query()->active()->where('event_type', 'external')->where('external_event', $event->name)->get() as $trigger) {
            if (! $this->matches($trigger->conditions, (array) $event->data)) {
                continue;
            }

            try {
                // Gli attributi si danno all'avvio: le azioni automatiche del primo passo (es. l'email al responsabile) partono subito e li leggono.
                $this->start->execute(null, $trigger->process_id, array_filter([
                    'title' => $this->title($event),
                    'external_ref' => $event->source && $event->subject_ref ? "{$event->source}:{$event->subject_ref}" : $event->subject_ref,
                    'external_data' => $event->data,
                    'company_id' => $this->companyId((array) $event->data),
                    'hard_deadline_at' => $this->hardDeadline((array) $event->data),
                ], fn ($value) => $value !== null));
                $started++;
            } catch (Throwable $e) {
                $failed = true;
                Log::error('Avvio del processo da evento esterno non riuscito.', ['event' => $event->name, 'trigger' => $trigger->id, 'error' => class_basename($e)]);
            }
        }

        $event->update(['started_count' => $started, 'status' => $failed && $started === 0 ? ExternalEvent::FAILED : ($started > 0 ? ExternalEvent::PROCESSED : ExternalEvent::IGNORED)]);

        return $event;
    }

    /**
     * Condizioni dei trigger: elenco di {field, operator (= o !=), value}; `field` può essere un percorso nei dati (es. product.type).
     *
     * @param  array<int, array{field: string, operator?: string, value: mixed}>|null  $conditions
     * @param  array<string, mixed>  $data
     */
    private function matches(?array $conditions, array $data): bool
    {
        foreach ((array) $conditions as $condition) {
            $actual = data_get($data, $condition['field'] ?? '');
            $same = (string) $actual === (string) ($condition['value'] ?? '');

            if (($condition['operator'] ?? '=') === '!=' ? $same : ! $same) {
                return false;
            }
        }

        return true;
    }

    /** Data tassativa indicata dall'evento (`data.hard_deadline`, AAAA-MM-GG): se c'è, la pratica nasce con quel termine. */
    private function hardDeadline(array $data): ?string
    {
        $date = data_get($data, 'hard_deadline');

        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    private function title(ExternalEvent $event): string
    {
        $labels = ['pratica.perfezionata' => 'Pratica perfezionata', 'segnalazione.ricevuta' => 'Segnalazione whistleblowing'];

        return trim(($labels[$event->name] ?? $event->name).' '.($event->subject_ref ?? ''));
    }

    /** @param array<string, mixed> $data */
    private function companyId(array $data): int|string|null
    {
        $vat = data_get($data, 'company_vat');

        return blank($vat) ? null : Company::query()->where('vat_number', $vat)->value('id');
    }
}
