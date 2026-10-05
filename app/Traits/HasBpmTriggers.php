<?php

namespace App\Traits;

use App\Filament\Actions\StartProcessAction;
use App\Models\ProcessTrigger;

trait HasBpmTriggers
{
    public static function bootHasBpmTriggers()
    {
        /**
         * PROCEDURA DI INIZIALIZZAZIONE TRIGGER BPM (BOOT TRAIT):
         * Questo metodo viene invocato automaticamente al boot del modello Eloquent che include il trait.
         * Nello specifico:
         * 1. Registra un hook sull'evento static::created() per valutare le regole BPM all'atto della creazione del record.
         * 2. Registra un hook sull'evento static::updated() per intercettare le modifiche allo stato o ad altri campi
         *    rilevanti e avviare il corrispondente workflow.
         */
        // 1. Trigger alla CREAZIONE
        static::created(function ($model) {
            self::checkAndRunTriggers($model, 'created');
        });

        // 2. Trigger all'AGGIORNAMENTO (Es: Passaggio immediato a "sospeso")
        static::updated(function ($model) {
            self::checkAndRunTriggers($model, 'updated');
        });
    }

    protected static function checkAndRunTriggers($model, string $eventType)
    {
        $triggers = ProcessTrigger::where('model_class', get_class($model))
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->get();

        foreach ($triggers as $trigger) {
            if (self::evaluateBpmConditions($model, $trigger->conditions, $eventType === 'updated')) {
                app(StartProcessAction::class)->execute($model, $trigger->process_id);
            }
        }
    }

    /**
     * Valuta se il record soddisfa le condizioni JSON configurate
     */
    protected static function evaluateBpmConditions($model, ?array $conditions, bool $checkIfDirty = false): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $cond) {
            $field = $cond['field'];
            $value = $cond['value'];

            // Se è un update, attiviamo il trigger SOLO se il campo è stato effettivamente modificato in questo salvataggio
            if ($checkIfDirty && ! $model->isDirty($field)) {
                return false;
            }

            if ($value != $model->$field) {
                return false;
            }
        }

        return true;
    }
}
