<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

class Process extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'version',
        'is_active',
        'is_periodic',
        'cron_expression',
        'target_model',
        'trigger_filters',
        'trigger_field',
        'trigger_state',
        'trigger_value',
        'exclude_field',
        'exclude_state',
        'exclude_value',
        'completion_write_field',
        'completion_write_value',
        'completion_write_app',
        'include_eligible_count_in_reminders',
        'last_activated_at',
        'next_run_at',
    ];

    /**
     * I cast dei tipi di dato (Sintassi Laravel 11).
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
            'is_periodic' => 'boolean',
            'trigger_filters' => 'array', // Converte automaticamente il JSON in array PHP
            'include_eligible_count_in_reminders' => 'boolean',
            'last_activated_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /**
     * Conta quanti record del `target_model` soddisfano oggi i criteri di
     * attivazione del processo (trigger_field/trigger_state/trigger_value,
     * exclude_field/exclude_state/exclude_value — la stessa logica usata da
     * BpmActivitiesController per l'endpoint /api/bpm/available-activities,
     * qui applicata come query diretta anziché valutata in memoria).
     * Ritorna null se il processo non ha un target_model configurato o se il
     * modello corrispondente non è risolvibile.
     */
    public function eligibleRecordsCount(): ?int
    {
        if (! $this->target_model) {
            return null;
        }

        $modelClass = Relation::getMorphedModel($this->target_model);

        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        $query = $modelClass::query();

        if ($this->trigger_field) {
            $field = $this->trigger_field;
            $query = match ($this->trigger_state) {
                'filled' => $query->whereNotNull($field)->where($field, '!=', ''),
                'empty' => $query->where(fn ($q) => $q->whereNull($field)->orWhere($field, '')),
                'equals' => $query->where($field, $this->trigger_value),
                default => $query,
            };
        }

        if ($this->exclude_field) {
            $field = $this->exclude_field;
            $query = match ($this->exclude_state) {
                'filled' => $query->where(fn ($q) => $q->whereNull($field)->orWhere($field, '')),
                'empty' => $query->whereNotNull($field)->where($field, '!=', ''),
                'equals' => $query->where($field, '!=', $this->exclude_value),
                default => $query,
            };
        }

        return $query->count();
    }

    /**
     * Relazione con i Task (gli step) che compongono questo processo.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProcessTask::class, 'process_id');
    }

    /**
     * Relazione con le Istanze (le pratiche reali) avviate da questo processo.
     */
    public function instances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class, 'process_id');
    }

    /**
     * Calcola e aggiorna la data del prossimo avvio in base all'espressione Cron.
     * * Questo metodo viene richiamato automaticamente dal Job di esecuzione
     * oppure può essere invocato manualmente/tramite un Observer quando l'amministratore
     * modifica l'espressione cron dal pannello di controllo.
     */
    public function updateNextRunDate(): void
    {
        if ($this->is_periodic && $this->cron_expression && CronExpression::isValidExpression($this->cron_expression)) {
            try {
                $cron = new CronExpression($this->cron_expression);

                // Calcola la prossima data valida a partire dal momento attuale
                $this->update([
                    'next_run_at' => $cron->getNextRunDate()->format('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                // In caso di errore nel parsing dell'espressione, resettiamo il campo
                $this->update(['next_run_at' => null]);
            }
        } else {
            // Se il processo non è periodico o non ha una stringa cron, azzeriamo la data
            $this->update(['next_run_at' => null]);
        }
    }
}
