<?php

namespace App\Services\Replies;

use App\Models\ProcessEmailReply;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskItem;
use App\Models\ProcessTaskItemAnswer;
use App\Models\User;

/**
 * Cosa fare di una risposta interpretata, secondo la configurazione dell'azione «attesa risposta email» (`config` dell'item):
 *  - `min_confidence` (80): sotto questa soglia decide un operatore;
 *  - `on_refuse`: `review` (default, decide un operatore), `answer` (il rifiuto è la risposta e il processo avanza), `suspend` (sospende il processo);
 *  - `auto` (true): se false non avanza mai da solo.
 * Una risposta applicata crea la risposta dell'azione: l'avanzamento del task lo fa l'observer esistente, come per ogni altra risposta.
 */
class ProcessReplyApplier
{
    public const ANSWER = 'answer';

    public const SUSPEND = 'suspend';

    public const REVIEW = 'review';

    /** Cosa fare in automatico con questa interpretazione. */
    public function decide(?Interpretation $interpretation, ProcessTaskItem $item): string
    {
        $config = (array) $item->config;

        if ($interpretation === null || ($config['auto'] ?? true) === false || $interpretation->confidence < (int) ($config['min_confidence'] ?? 80)) {
            return self::REVIEW;
        }

        return match ($interpretation->intent) {
            Interpretation::CONFIRM => self::ANSWER,
            Interpretation::REFUSE => match ($config['on_refuse'] ?? 'review') {
                'answer' => self::ANSWER,
                'suspend' => self::SUSPEND,
                default => self::REVIEW,
            },
            default => self::REVIEW,
        };
    }

    /** Applica: crea la risposta dell'azione (o sospende il processo) e chiude la riga della risposta. */
    public function apply(ProcessEmailReply $reply, string $how, ?User $by = null, ?string $note = null): ?ProcessTaskItemAnswer
    {
        $answer = null;

        if ($how === self::SUSPEND) {
            $reply->instance->update(['status' => 'suspended']);
        } elseif ($how === self::ANSWER && $reply->process_task_item_id) {
            $human = $by !== null;
            $answer = ProcessTaskItemAnswer::create([
                'process_instance_id' => $reply->process_instance_id,
                'process_task_execution_id' => $reply->process_task_execution_id,
                'process_task_item_id' => $reply->process_task_item_id,
                'value_boolean' => $reply->intent === Interpretation::CONFIRM ? true : ($reply->intent === Interpretation::REFUSE ? false : null),
                'value_text' => trim("Risposta email da {$reply->from_email}: ".($note ?: $reply->summary ?: $reply->excerpt)),
                'user_id' => $by?->getKey() ?? 0,
                'operator_type' => $human ? 'human' : ($reply->source === 'ai' ? 'ai' : 'procedural'),
                'operator_label' => $human ? 'email_reply.operator' : 'email_reply.'.($reply->source ?: 'rule'),
                'confidence' => $reply->confidence,
                'completed_at' => now(),
            ]);
        }

        $reply->update([
            'status' => ProcessEmailReply::APPLIED,
            'decided_by' => $by?->getKey(),
            'decided_at' => now(),
            'process_task_item_answer_id' => $answer?->id,
        ]);

        activity('bpm')
            ->performedOn($reply->instance)
            ->event('email_reply_applied')
            ->withProperties(['intent' => $reply->intent, 'confidence' => $reply->confidence, 'how' => $how, 'by' => $by ? 'operator' : ($reply->source ?: 'rule')])
            ->log('Risposta email applicata al processo.');

        return $answer;
    }

    public function ignore(ProcessEmailReply $reply, User $by): void
    {
        $reply->update(['status' => ProcessEmailReply::IGNORED, 'decided_by' => $by->getKey(), 'decided_at' => now()]);
    }

    public function pendingItem(ProcessInstance $instance, ?int $executionId): ?ProcessTaskItem
    {
        $execution = $executionId ? $instance->taskExecutions()->find($executionId) : $instance->currentTaskExecution;

        return $execution?->pendingItemsOfType(['await_email_reply'])->first();
    }
}
