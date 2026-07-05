<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    protected $fillable = [
        'name',
        'code',
        'version',
        'is_active',
        'is_periodic',
        'cron_expression',
        'target_model',
        'target_filters',
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
            'target_filters' => 'array', // Converte automaticamente il JSON in array PHP
            'last_activated_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
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
