<?php

namespace App\Services\Replies;

use App\Models\ProcessEmailReply;
use App\Models\ProcessInstance;
use App\Models\ProcessPostponement;
use App\Models\ProcessTaskExecution;
use App\Models\ProcessTaskItem;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Rinvii chiesti dalla controparte per email. Concedere un rinvio sposta il termine dello step corrente (`postponed_until`, che
 * `effectiveDueAt()` rispetta e che ferma l'escalation fino a quella data) e lascia una riga di storico; lo step resta aperto.
 * In automatico (`on_postpone` = grant) solo entro i limiti dell'azione: `max_postpone_days` (30) oltre il termine attuale,
 * `max_postponements` (2) rinvii per step, mai oltre la data tassativa della pratica. Fuori dai limiti decide un operatore.
 */
class PostponementService
{
    /** Motivo per cui il rinvio non si può concedere in automatico; null se è nei limiti. */
    public function violation(ProcessTaskExecution $execution, ProcessTaskItem $item, Carbon $proposed): ?string
    {
        $config = (array) $item->config;
        $today = now()->startOfDay();

        if ($proposed->lte($today)) {
            return 'La data proposta non è futura.';
        }

        $base = $execution->effectiveDueAt() ?? $today;

        if ($proposed->gt($base->copy()->startOfDay()->addDays((int) ($config['max_postpone_days'] ?? 30)))) {
            return 'La data proposta supera i giorni di rinvio consentiti.';
        }

        if ($this->count($execution) >= (int) ($config['max_postponements'] ?? 2)) {
            return 'Raggiunto il numero massimo di rinvii per questo step.';
        }

        $hard = $execution->processInstance?->hard_deadline_at;

        if ($hard !== null && $proposed->gt($hard)) {
            return 'La data proposta supera la data tassativa della pratica.';
        }

        return null;
    }

    public function count(ProcessTaskExecution $execution): int
    {
        return ProcessPostponement::query()->where('process_task_execution_id', $execution->id)->count();
    }

    /** Concede il rinvio. Con `$moveHardDeadline` sposta anche la data tassativa della pratica, se la nuova data la supera. */
    public function grant(ProcessTaskExecution $execution, Carbon $to, ?User $by = null, ?ProcessEmailReply $reply = null, bool $moveHardDeadline = false): ProcessPostponement
    {
        $from = $execution->effectiveDueAt();
        $instance = $execution->processInstance;
        $moved = false;

        $execution->update(['postponed_until' => $to->toDateString()]);

        if ($moveHardDeadline && $instance->hard_deadline_at !== null && $to->gt($instance->hard_deadline_at)) {
            $instance->update(['hard_deadline_at' => $to->toDateString()]);
            $moved = true;
        }

        $postponement = ProcessPostponement::create([
            'process_instance_id' => $execution->process_instance_id,
            'process_task_execution_id' => $execution->id,
            'process_email_reply_id' => $reply?->id,
            'from_at' => $from,
            'to_date' => $to->toDateString(),
            'granted_by' => $by?->getKey(),
            'moved_hard_deadline' => $moved,
            'reason' => $reply?->summary ? mb_substr($reply->summary, 0, 500) : null,
        ]);

        activity('bpm')
            ->performedOn($instance)
            ->event('postponement_granted')
            ->withProperties(['to' => $to->toDateString(), 'by' => $by ? 'operator' : 'automatic', 'moved_hard_deadline' => $moved])
            ->log("Rinvio concesso fino al {$to->format('d/m/Y')} per lo step {$execution->processTask?->name}.");

        return $postponement;
    }
}
