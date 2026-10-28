<?php

namespace App\Services;

use App\Enums\Severity;
use App\Models\ProcessCheckNotification;
use App\Models\ProcessTaskItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Decide se un controllo esterno che segnala ancora il problema deve rimandare l'avviso. Dopo un avviso si attendono i giorni di
 * ripetizione del suo grado; poi, se il problema c'è ancora, l'avviso sale al grado successivo (regular > warning > alert) e allarga
 * i destinatari. Se il controllo peggiora prima, l'avviso parte subito col nuovo grado. Con il controllo tornato «ok» la serie si chiude.
 *
 * Giorni di ripetizione, in ordine: `config.repeat_days` dell'azione, `reminder_repeat_days` del processo, `services.reminder_repeat_days`.
 */
class ReminderPolicy
{
    /**
     * Grado con cui mandare l'avviso, o null se per oggi non va rimandato.
     */
    public function severityToSend(ProcessTaskItem $item, Severity $checked, ?Carbon $now = null): ?Severity
    {
        $last = $this->openSeries($item)?->first();

        if ($last === null || $checked->level() > $last->effective_severity->level()) {
            return $checked;
        }

        $now ??= now();
        $waited = (int) $last->sent_at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay());

        if ($waited < $this->repeatDays($item, $last->effective_severity)) {
            return null;
        }

        return $this->next($last->effective_severity);
    }

    /** Registra l'avviso partito, aggiungendolo alla serie del problema in corso. */
    public function recordSent(ProcessTaskItem $item, Severity $checked, Severity $sent): void
    {
        if (! $item->exists) {
            return;
        }

        $level = (int) ($this->openSeries($item)?->max('level') ?? 0) + 1;

        ProcessCheckNotification::query()->create([
            'process_task_item_id' => $item->getKey(),
            'severity' => $checked,
            'effective_severity' => $sent,
            'level' => $level,
            'sent_at' => now(),
        ]);
    }

    /** Il controllo è tornato ok: il prossimo problema ricomincia dal suo grado. */
    public function resolve(ProcessTaskItem $item): void
    {
        if (! $item->exists) {
            return;
        }

        ProcessCheckNotification::query()
            ->where('process_task_item_id', $item->getKey())
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    /** Giorni di attesa prima di rimandare un avviso di questo grado. */
    public function repeatDays(ProcessTaskItem $item, Severity $severity): int
    {
        $value = data_get($item->config, "repeat_days.{$severity->value}")
            ?? data_get($item->task?->process?->reminder_repeat_days, $severity->value)
            ?? data_get(config('services.reminder_repeat_days'), $severity->value, 1);

        return max(1, (int) $value);
    }

    private function next(Severity $severity): Severity
    {
        return match ($severity) {
            Severity::Regular => Severity::Warning,
            default => Severity::Alert,
        };
    }

    /** @return Collection<int, ProcessCheckNotification>|null avvisi del problema in corso, dal più recente */
    private function openSeries(ProcessTaskItem $item)
    {
        if (! $item->exists) {
            return null;
        }

        $series = ProcessCheckNotification::query()
            ->where('process_task_item_id', $item->getKey())
            ->whereNull('resolved_at')
            ->orderByDesc('sent_at')->orderByDesc('id')
            ->get();

        return $series->isEmpty() ? null : $series;
    }
}
