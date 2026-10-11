<?php

namespace App\Services\Replies;

use App\Models\ProcessInstance;
use App\Models\ProcessTaskExecution;
use App\Support\ProcessThread;
use Unico\Core\Models\IncomingEmail;

/**
 * Collega una email in arrivo a un processo: prima con l'identificativo di conversazione che le email dei processi portano
 * (In-Reply-To / References), poi, per le email partite in altro modo, con «[ID: n]» nell'oggetto. Solo processi in corso.
 */
class ProcessReplyMatcher
{
    /** @return array{instance: ProcessInstance, execution: ?ProcessTaskExecution, current: bool}|null */
    public function match(IncomingEmail $email): ?array
    {
        $thread = ProcessThread::parse($email->in_reply_to) ?? ProcessThread::parse($email->references);

        if ($thread === null && preg_match('/\[ID:\s*(\d+)\]/', (string) $email->subject, $m)) {
            $thread = ['instance' => (int) $m[1], 'execution' => null];
        }

        if ($thread === null) {
            return null;
        }

        $instance = ProcessInstance::query()->find($thread['instance']);

        if ($instance === null || $instance->status !== 'in_progress') {
            return null;
        }

        $current = $instance->currentTaskExecution;
        $execution = $thread['execution'] === null
            ? $current
            : ProcessTaskExecution::query()->where('process_instance_id', $instance->id)->find($thread['execution']);

        // Una risposta a un passo già concluso non fa avanzare niente: si registra ma non si applica.
        return ['instance' => $instance, 'execution' => $execution, 'current' => $execution !== null && $current?->id === $execution->id];
    }
}
