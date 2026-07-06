<?php

namespace App\Traits;

use App\Actions\StartProcessAction;
use App\Models\ProcessTrigger;

trait HasBpmTriggers
{
    public static function bootHasBpmTriggers()
    {
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
