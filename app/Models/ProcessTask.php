<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessTask extends Model
{
    protected $orderBy = 'ordine';

    protected $fillable = [
        'process_id',
        'name',
        'code',
        'description',
        'ordine',
        'business_function_id',
        'trigger_field',
        'trigger_state',
        'trigger_value',
        'exclude_field',
        'exclude_state',
        'exclude_value',
        'condition_field',
        'condition_operator',
        'condition_value',
        'has_reminders',
        'reminder_interval_days',
        'max_reminders',
        'escalation_rules',
    ];

    protected function casts(): array
    {
        return [
            'ordine' => 'integer',
            'has_reminders' => 'boolean',
            'reminder_interval_days' => 'integer',
            'max_reminders' => 'integer',
            'escalation_rules' => 'array', // Converte automaticamente il JSON in array PHP
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function processTaskItems(): HasMany
    {
        return $this->hasMany(ProcessTaskItem::class)->orderBy('ordine');
    }

    public function raciAssignments(): HasMany
    {
        return $this->hasMany(ProcessTaskRaci::class, 'process_task_id');
    }

    /**
     * Vero se questo task va eseguito per il soggetto indicato, in base alle condizioni
     * trigger_field/trigger_state/trigger_value (il task si applica solo se soddisfatte) ed
     * exclude_field/exclude_state/exclude_value (il task viene saltato se soddisfatte).
     * Stessa semantica filled/empty/equals già usata da Process::eligibleRecordsCount() e
     * BpmActivitiesController, per coerenza in tutto il motore BPM.
     * Un soggetto assente (pratiche interne/ricorrenti) rende sempre applicabile il task:
     * non c'è alcun record su cui valutare le condizioni.
     */
    public function isApplicableTo(?Model $subject): bool
    {
        if (! $subject) {
            return true;
        }

        if (filled($this->exclude_field)) {
            $value = data_get($subject, $this->exclude_field);

            $excluded = match ($this->exclude_state) {
                'filled' => filled($value),
                'empty' => blank($value),
                'equals' => (string) $value === (string) $this->exclude_value,
                default => false,
            };

            if ($excluded) {
                return false;
            }
        }

        if (filled($this->trigger_field)) {
            $value = data_get($subject, $this->trigger_field);

            return match ($this->trigger_state) {
                'filled' => filled($value),
                'empty' => blank($value),
                'equals' => (string) $value === (string) $this->trigger_value,
                default => true,
            };
        }

        return true;
    }

    /**
     * Il primo task (per ordine) del processo che si applica al soggetto indicato.
     */
    public static function firstApplicableTask(int $processId, ?Model $subject): ?self
    {
        return static::where('process_id', $processId)
            ->orderBy('ordine')
            ->get()
            ->first(fn (self $task) => $task->isApplicableTo($subject));
    }

    /**
     * Il successivo task (per ordine crescente) dopo $afterOrdine che si applica al soggetto,
     * saltando quelli le cui condizioni trigger/esclusione non sono soddisfatte.
     */
    public static function nextApplicableTask(int $processId, int $afterOrdine, ?Model $subject): ?self
    {
        return static::where('process_id', $processId)
            ->where('ordine', '>', $afterOrdine)
            ->orderBy('ordine')
            ->get()
            ->first(fn (self $task) => $task->isApplicableTo($subject));
    }

    /**
     * Il precedente task (per ordine decrescente) prima di $beforeOrdine che si applica al
     * soggetto, usato quando una pratica viene respinta e rispedita indietro nel workflow.
     */
    public static function previousApplicableTask(int $processId, int $beforeOrdine, ?Model $subject): ?self
    {
        return static::where('process_id', $processId)
            ->where('ordine', '<', $beforeOrdine)
            ->orderByDesc('ordine')
            ->get()
            ->first(fn (self $task) => $task->isApplicableTo($subject));
    }
}
